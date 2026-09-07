import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import { extractLegacyDoc, LegacyWordPreviewError } from '../legacy-word.js';
import OleCompoundDoc from 'word-extractor/lib/ole-compound-doc.js';
import BufferReader from 'word-extractor/lib/buffer-reader.js';

const fixture = async (name) => fs.readFile(new URL(`./fixtures/${name}`, import.meta.url));
for (const name of ['test01.doc', 'test03.doc', 'test04.doc', 'test11.doc', 'test15.doc']) {
    const bytes = await fixture(name);
    const result = await extractLegacyDoc(bytes.buffer.slice(bytes.byteOffset, bytes.byteOffset + bytes.byteLength));
    assert.equal(typeof result.body, 'string');
    assert.ok(result.body.trim().length > 10, `${name}: expected real Word text`);
    assert.ok(!result.body.includes('\u0000'), `${name}: binary control bytes leaked as preview text`);
    if (name === 'test11.doc') assert.match(result.body, /[\u3040-\u30ff\u3400-\u9fff\uac00-\ud7af]/u, 'Asian text was lost');
    if (name === 'test03.doc') assert.ok(result.body.includes('\t'), 'Table cells lost their text separators');
    if (name === 'test15.doc') {
        assert.match(result.headers, /header/i);
        assert.doesNotMatch(result.headers, /footer/i);
        assert.match(result.footers, /footer/i);
        assert.doesNotMatch(result.footers, /header/i);
    }
    console.log(`${name}: ${result.body.length} body characters, ${result.headers.length} header characters, ${result.footers.length} footer characters`);
}

const bytes = await fixture('test01.doc');
const compound = new OleCompoundDoc(new BufferReader(bytes));
await compound.read();
const wordEntry = compound._directoryTree.root.streams.WordDocument;
const wordOffset = wordEntry.size < compound._header.shortStreamMax
    ? compound._getFileOffsetForShortSec(wordEntry.secId)
    : compound._getFileOffsetForSec(wordEntry.secId);
const encrypted = Buffer.from(bytes);
encrypted.writeUInt16LE(encrypted.readUInt16LE(wordOffset + 10) | 0x0100, wordOffset + 10);
await assert.rejects(extractLegacyDoc(encrypted), (error) => error instanceof LegacyWordPreviewError && error.code === 'encrypted');
const old = Buffer.from(bytes);
old.writeUInt16LE(0x0065, wordOffset + 2);
await assert.rejects(extractLegacyDoc(old), (error) => error.code === 'unsupported');
await assert.rejects(extractLegacyDoc(Buffer.from('This is not a Word document.')), (error) => error.code === 'unsupported');
await assert.rejects(extractLegacyDoc(new Uint8Array(25 * 1024 * 1024 + 1)), (error) => error.code === 'too_large');
await assert.rejects(extractLegacyDoc(bytes.subarray(0, bytes.length / 2)), (error) => error.code === 'unreadable');

// A two-node FAT cycle must reject instead of looping or allocating indefinitely.
const fatCycle = Buffer.from(bytes);
const directorySector = compound._header.dirSecId;
const otherSector = directorySector === 0 ? 1 : 0;
const fatOffset = compound._getFileOffsetForSec(compound._MSAT[0]);
fatCycle.writeInt32LE(otherSector, fatOffset + directorySector * 4);
fatCycle.writeInt32LE(directorySector, fatOffset + otherSector * 4);
await assert.rejects(extractLegacyDoc(fatCycle), (error) => error.code === 'unreadable');

// A directory sibling cycle is separately bounded, including self-reference.
const directoryCycle = Buffer.from(bytes);
const childId = compound._directoryTree.root.storageDirId;
const directoryChain = compound._SAT.getSecIdChain(directorySector);
const childByte = childId * 128;
const childOffset = compound._getFileOffsetForSec(directoryChain[Math.floor(childByte / compound._header.secSize)])
    + childByte % compound._header.secSize;
directoryCycle.writeInt32LE(childId, childOffset + 68);
await assert.rejects(extractLegacyDoc(directoryCycle), (error) => error.code === 'unreadable');

const framed = new Uint8Array(bytes.length + 20);
framed.set(bytes, 10);
assert.deepEqual(await extractLegacyDoc(framed.subarray(10, -10)), await extractLegacyDoc(bytes), 'Typed-array byte offsets were ignored');
console.log('LEGACY_WORD_PREVIEW_OK: five real binary documents; Unicode, tables, headers/footers, encrypted/unsupported input, bounds and corrupt-cycle handling.');
