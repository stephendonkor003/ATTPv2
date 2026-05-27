@php
    $gallaryDir = public_path('gallary');
    $allGallaryFiles = collect(\Illuminate\Support\Facades\File::files($gallaryDir))
        ->filter(fn($f) => in_array(strtolower($f->getExtension()), ['jpg', 'jpeg', 'png', 'webp']))
        ->map(fn($f) => $f->getFilename())
        ->values()
        ->all();
    shuffle($allGallaryFiles);
    $stripImages = array_slice($allGallaryFiles, 0, 12);
@endphp

@if(count($stripImages) > 0)
<section class="gallery-strip-section">
    <div class="gallery-strip-header">
        <h2>From Our Gallery</h2>
        <p>Moments from our events and activities across Africa</p>
        <a href="{{ route('gallery') }}">View Full Gallery &rarr;</a>
    </div>

    <div class="gallery-strip-grid">
        @foreach($stripImages as $i => $img)
        <div class="gallery-strip-item" onclick="openStripLightbox({{ $i }})">
            <img src="{{ asset('gallary/' . rawurlencode($img)) }}" alt="ATTP Gallery" loading="lazy">
        </div>
        @endforeach
    </div>
</section>

<div class="gallery-lightbox" id="stripLightbox">
    <div class="gallery-lightbox-overlay" onclick="closeStripLightbox()"></div>
    <div class="gallery-lightbox-inner">
        <button class="gallery-lightbox-btn" onclick="prevStripImage()">&#8249;</button>
        <div class="gallery-lightbox-img-wrap">
            <button class="gallery-lightbox-close" onclick="closeStripLightbox()">&times;</button>
            <img id="stripLightboxImg" src="" alt="Gallery Image">
            <div class="gallery-lightbox-counter" id="stripLightboxCounter"></div>
        </div>
        <button class="gallery-lightbox-btn" onclick="nextStripImage()">&#8250;</button>
    </div>
</div>

<script>
(function () {
    var stripImgs = @json($stripImages);
    var stripIdx = 0;

    window.openStripLightbox = function (i) {
        stripIdx = i;
        updateStrip();
        document.getElementById('stripLightbox').classList.add('active');
        document.body.style.overflow = 'hidden';
    };
    window.closeStripLightbox = function () {
        document.getElementById('stripLightbox').classList.remove('active');
        document.body.style.overflow = '';
    };
    window.prevStripImage = function () {
        stripIdx = (stripIdx - 1 + stripImgs.length) % stripImgs.length;
        updateStrip();
    };
    window.nextStripImage = function () {
        stripIdx = (stripIdx + 1) % stripImgs.length;
        updateStrip();
    };
    function updateStrip() {
        document.getElementById('stripLightboxImg').src =
            '{{ rtrim(asset("gallary"), "/") }}/' + encodeURIComponent(stripImgs[stripIdx]);
        document.getElementById('stripLightboxCounter').textContent =
            (stripIdx + 1) + ' / ' + stripImgs.length;
    }
    document.addEventListener('keydown', function (e) {
        if (!document.getElementById('stripLightbox').classList.contains('active')) return;
        if (e.key === 'ArrowRight') window.nextStripImage();
        else if (e.key === 'ArrowLeft') window.prevStripImage();
        else if (e.key === 'Escape') window.closeStripLightbox();
    });
}());
</script>
@endif
