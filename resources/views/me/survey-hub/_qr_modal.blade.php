<div class="modal fade" id="surveyQrModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="surveyQrModalTitle">Survey QR Code</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img src="" alt="Survey QR code" id="surveyQrModalImage" class="img-fluid rounded border p-2 bg-white">
                <div class="small text-muted mt-3" id="surveyQrModalLink"></div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary js-copy-survey-qr-link">Copy Link</button>
                <button type="button" class="btn btn-primary js-download-survey-qr">Download PNG</button>
            </div>
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                async function copyText(text) {
                    try {
                        await navigator.clipboard.writeText(text);
                    } catch (error) {
                        window.prompt('Copy this link:', text);
                    }
                }

                async function downloadQrPng(qrUrl, titleText) {
                    if (!qrUrl) {
                        return;
                    }

                    try {
                        const response = await fetch(qrUrl);
                        const blob = await response.blob();
                        const objectUrl = URL.createObjectURL(blob);
                        const anchor = document.createElement('a');
                        anchor.href = objectUrl;
                        anchor.download = `${(titleText || 'survey-qr').toLowerCase().replace(/[^a-z0-9]+/g, '-')}.png`;
                        document.body.appendChild(anchor);
                        anchor.click();
                        anchor.remove();
                        URL.revokeObjectURL(objectUrl);
                    } catch (error) {
                        window.open(qrUrl, '_blank', 'noopener');
                    }
                }

                document.querySelectorAll('[data-copy-text]').forEach((button) => {
                    button.addEventListener('click', () => {
                        const text = button.dataset.copyText || '';
                        if (!text) {
                            return;
                        }

                        copyText(text);
                    });
                });

                document.querySelectorAll('[data-download-qr]').forEach((button) => {
                    button.addEventListener('click', () => {
                        downloadQrPng(button.dataset.downloadQr || '', button.dataset.downloadTitle || '');
                    });
                });

                const modalElement = document.getElementById('surveyQrModal');
                const image = document.getElementById('surveyQrModalImage');
                const title = document.getElementById('surveyQrModalTitle');
                const link = document.getElementById('surveyQrModalLink');
                const modal = modalElement && window.bootstrap ? new bootstrap.Modal(modalElement) : null;
                const state = {
                    link: '',
                    qr: '',
                    title: 'Survey QR Code',
                };

                document.querySelectorAll('.js-open-survey-qr').forEach((button) => {
                    button.addEventListener('click', () => {
                        state.link = button.dataset.link || '';
                        state.qr = button.dataset.qr || '';
                        state.title = button.dataset.title || 'Survey QR Code';

                        if (title) {
                            title.textContent = state.title;
                        }

                        if (image) {
                            image.src = state.qr;
                        }

                        if (link) {
                            link.textContent = state.link;
                        }

                        modal?.show();
                    });
                });

                document.querySelector('.js-copy-survey-qr-link')?.addEventListener('click', () => {
                    if (!state.link) {
                        return;
                    }

                    copyText(state.link);
                });

                document.querySelector('.js-download-survey-qr')?.addEventListener('click', async () => {
                    downloadQrPng(state.qr, state.title);
                });
            });
        </script>
    @endpush
@endonce
