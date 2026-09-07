import { Buffer } from 'buffer';
import WordOleExtractor from 'word-extractor/lib/word-ole-extractor.js';
import BufferReader from 'word-extractor/lib/buffer-reader.js';
import AllocationTable from 'word-extractor/lib/ole-allocation-table.js';
import DirectoryTree from 'word-extractor/lib/ole-directory-tree.js';
import StorageStream from 'word-extractor/lib/ole-storage-stream.js';

const MAX_FILE_BYTES = 25 * 1024 * 1024;
const OLE_SIGNATURE = [0xd0, 0xcf, 0x11, 0xe0, 0xa1, 0xb1, 0x1a, 0xe1];

export class LegacyWordPreviewError extends Error {
    constructor(code, message) {
        super(message);
        this.name = 'LegacyWordPreviewError';
        this.code = code;
    }
}

const corrupt = () => new LegacyWordPreviewError('unreadable', 'This older Word document could not be read. Open the original file or save a copy as .docx.');

const readStream = StorageStream.prototype._read;
StorageStream.prototype._read = function () {
    try {
        Promise.resolve(readStream.call(this)).catch((error) => this.destroy(error));
    } catch (error) {
        this.destroy(error);
    }
};

// The pinned extractor handles a self-loop but not a longer corrupt sector
// cycle. Bound every traversal to its actual input so a bad file cannot hang
// the page or cause reads outside the selected file. These methods are used
// only by this binary .doc adapter; the library's filesystem entry is absent.
AllocationTable.prototype.getSecIdChain = function (startSecId) {
    const ids = [];
    const seen = new Set();
    const sectorSize = this === this._doc._SSAT ? this._doc._header.shortSecSize : this._doc._header.secSize;
    const maximum = Math.ceil(this._doc._reader.buffer().length / sectorSize);
    let id = startSecId;
    while (id >= 0) {
        if (!Number.isInteger(id) || id >= this._table.length || id >= maximum || seen.has(id) || ids.length >= maximum) throw corrupt();
        seen.add(id);
        ids.push(id);
        id = this._table[id];
    }
    if (id !== -1 && id !== -2) throw corrupt();
    return ids;
};

DirectoryTree.prototype._getChildIds = function (storageEntry) {
    if (!storageEntry) throw corrupt();
    const result = [];
    const seen = new Set();
    const pending = [storageEntry.storageDirId];
    while (pending.length) {
        const id = pending.pop();
        if (id === -1) continue;
        if (!Number.isInteger(id) || id < 0 || id >= this._entries.length || seen.has(id)) throw corrupt();
        seen.add(id);
        result.push(id);
        const entry = this._entries[id];
        pending.push(entry.right, entry.left);
    }
    return result;
};

DirectoryTree.prototype._buildHierarchy = function (root) {
    const pending = [root];
    const seen = new Set();
    while (pending.length) {
        const storage = pending.pop();
        if (!storage || seen.has(storage)) throw corrupt();
        seen.add(storage);
        storage.storages = Object.create(null);
        storage.streams = Object.create(null);
        for (const id of this._getChildIds(storage)) {
            const entry = this._entries[id];
            if (entry.type === 1) {
                storage.storages[entry.name] = entry;
                pending.push(entry);
            } else if (entry.type === 2) {
                if (entry.size < 0 || entry.size > this._doc._reader.buffer().length) throw corrupt();
                storage.streams[entry.name] = entry;
            }
        }
    }
};

class BoundedBufferReader extends BufferReader {
    read(destination, offset, length, position) {
        if (![offset, length, position].every(Number.isSafeInteger) || Math.min(offset, length, position) < 0
            || position + length > this._buffer.length || offset + length > destination.length) {
            return Promise.reject(corrupt());
        }
        return super.read(destination, offset, length, position);
    }
}

class PreviewWordExtractor extends WordOleExtractor {
    extractWordDocument(document, wordStream) {
        if (wordStream.length < 0x1aa || wordStream.readUInt16LE(0) !== 0xa5ec) throw corrupt();
        const flags = wordStream.readUInt16LE(0x0a);
        if (flags & 0x8100) {
            throw new LegacyWordPreviewError('encrypted', 'This Word document is password protected. Open the original file in Word to view it.');
        }
        if (wordStream.readUInt16LE(2) < 0x00c1) {
            throw new LegacyWordPreviewError('unsupported', 'This Word format is older than Word 97. Open the original file or save a copy as .docx.');
        }
        return super.extractWordDocument(document, wordStream);
    }
}

/** Read the selected binary Word file locally; no fetch, upload or DOM writes. */
export async function extractLegacyDoc(input) {
    if (!(input instanceof ArrayBuffer) && !ArrayBuffer.isView(input)) {
        throw new LegacyWordPreviewError('invalid_input', 'Select a Word document to preview.');
    }
    const bytes = input instanceof ArrayBuffer
        ? Buffer.from(input)
        : Buffer.from(input.buffer, input.byteOffset, input.byteLength);
    if (bytes.length > MAX_FILE_BYTES) {
        throw new LegacyWordPreviewError('too_large', 'Older Word text preview supports files up to 25 MB. Open the original file to view this document.');
    }
    if (bytes.length < 512 || !OLE_SIGNATURE.every((value, index) => bytes[index] === value)) {
        throw new LegacyWordPreviewError('unsupported', 'This file is not a supported Word 97–2003 document. Open the original file or save a copy as .docx.');
    }
    const sectorShift = bytes.readUInt16LE(30);
    if (![9, 12].includes(sectorShift) || bytes.readUInt16LE(32) !== 6 || bytes.readUInt16LE(28) !== 0xfffe) throw corrupt();
    const sectors = Math.ceil(bytes.length / (2 ** sectorShift));
    if ([44, 64, 72].some((offset) => bytes.readUInt32LE(offset) > sectors)) throw corrupt();

    const reader = new BoundedBufferReader(bytes);
    await reader.open();
    try {
        const document = await new PreviewWordExtractor().extract(reader);
        const result = {
            body: document.getBody(),
            headers: document.getHeaders({ includeFooters: false }),
            footers: document.getFooters(),
            footnotes: document.getFootnotes(),
            endnotes: document.getEndnotes(),
            textboxes: document.getTextboxes(),
            annotations: document.getAnnotations(),
        };
        for (const [key, value] of Object.entries(result)) result[key] = typeof value === 'string' ? value.replace(/\u0000/g, '') : '';
        return result;
    } catch (error) {
        if (error instanceof LegacyWordPreviewError) throw error;
        throw corrupt();
    } finally {
        await reader.close();
    }
}
