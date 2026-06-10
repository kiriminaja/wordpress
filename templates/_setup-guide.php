<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Setup Guide notice.
 *
 * @var array $steps              Each step: ['title','description','done','required','url']
 * @var int   $done_count         Number of completed steps.
 * @var int   $current_step_index First incomplete step index.
 */

$kiriof_total_steps   = count( $steps );
$kiriof_current_index = isset( $current_step_index ) ? max( 0, min( (int) $current_step_index, max( 0, $kiriof_total_steps - 1 ) ) ) : 0;
$kiriof_notice_id     = 'kiriof-setup-guide-' . wp_rand( 1000, 9999 );
global $pagenow;
$kiriof_is_dashboard = ( 'index.php' === $pagenow );
?>
<div
    id="<?php echo esc_attr( $kiriof_notice_id ); ?>"
    class="<?php echo esc_attr( $kiriof_is_dashboard ? 'postbox' : 'notice' ); ?>"
    style="<?php echo esc_attr( $kiriof_is_dashboard ? 'max-width:none;margin:16px 0;padding:0;' : 'padding:0;max-width:none;margin-bottom:16px;border:1px solid #d0d4da;border-left-width:1px;border-left-color:#d0d4da;border-radius:12px;overflow:hidden;background:#fff' ); ?>"
>
    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:18px 20px;border-bottom:1px solid #e6e8eb;background:#fff">
        <div style="display:flex;align-items:center;gap:12px;min-width:0">
            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="28" height="28" viewBox="0 0 28 28" style="flex-shrink:0">
                <defs>
                    <linearGradient id="kiriof-sg-grad0" gradientUnits="userSpaceOnUse" x1="128" y1="0" x2="128" y2="256" gradientTransform="matrix(0.109375,0,0,0.109375,0,0)">
                        <stop offset="0" style="stop-color:rgb(100%,11.372549%,74.509804%);stop-opacity:1"/>
                        <stop offset="0.13" style="stop-color:rgb(87.45098%,11.372549%,74.117647%);stop-opacity:1"/>
                        <stop offset="0.3" style="stop-color:rgb(73.333333%,11.764706%,73.72549%);stop-opacity:1"/>
                        <stop offset="0.48" style="stop-color:rgb(62.352941%,11.764706%,73.333333%);stop-opacity:1"/>
                        <stop offset="0.65" style="stop-color:rgb(54.509804%,11.764706%,73.333333%);stop-opacity:1"/>
                        <stop offset="0.83" style="stop-color:rgb(49.803922%,11.764706%,73.333333%);stop-opacity:1"/>
                        <stop offset="1" style="stop-color:rgb(48.235294%,12.156863%,73.333333%);stop-opacity:1"/>
                    </linearGradient>
                    <linearGradient id="kiriof-sg-grad1" gradientUnits="userSpaceOnUse" x1="195.660004" y1="85.4842" x2="59.348301" y2="155.867004" gradientTransform="matrix(0.109375,0,0,0.109375,0,0)">
                        <stop offset="0" style="stop-color:rgb(100%,100%,100%);stop-opacity:1"/>
                        <stop offset="0.99" style="stop-color:rgb(94.509804%,94.509804%,94.509804%);stop-opacity:0.501961"/>
                    </linearGradient>
                    <linearGradient id="kiriof-sg-grad2" gradientUnits="userSpaceOnUse" x1="73.133102" y1="156.063995" x2="170.735001" y2="155.994995" gradientTransform="matrix(0.109375,0,0,0.109375,0,0)">
                        <stop offset="0" style="stop-color:rgb(100%,100%,100%);stop-opacity:0.101961"/>
                        <stop offset="1" style="stop-color:rgb(94.509804%,94.509804%,94.509804%);stop-opacity:0.8"/>
                    </linearGradient>
                </defs>
                <rect x="0" y="0" width="28" height="28" rx="6" ry="6" style="fill:url(#kiriof-sg-grad0)"/>
                <path style="fill:url(#kiriof-sg-grad1)" d="M19.082 6.336l-2.188 6.645c-.152.527-.59.727-.894.445l-.606-.586-.289-.293-1.593 1.473c-3.11 3.379.077 6.57.077 6.57l-4.933-4.933c-.016-.016-.035-.031-.051-.047-.012-.016-.024-.031-.04-.043l-.03-.035c-.829-.922-.759-2.285.128-3.176l3.105-3.164-.27-.273-.593-.535c-.305-.281-.191-.789.2-.91l7.23-1.645c.394-.125.773.227.746.508z"/>
                <path style="fill:url(#kiriof-sg-grad2)" d="M17.973 18.473c.937.937.945 2.39.03 3.3-.917.907-2.347.864-3.277-.066l-1.238-1.23c-.52-.586-2.746-3.446.023-6.457zm-9.407-2.907c.016.012.028.028.04.043.015.016.035.031.05.047l3.375 3.375-3.316-3.3c-.067-.063-.125-.13-.18-.2zm-.03-.035c-.013-.012-.02-.024-.032-.04.012.016.02.028.032.04z"/>
            </svg>
            <strong style="font-size:14px;line-height:1.4"><?php echo esc_html__( 'Panduan Setup KiriminAja', 'kiriminaja-official' ); ?></strong>
        </div>
        <div style="display:flex;align-items:center;gap:6px;flex-shrink:0">
            <button type="button" class="button-link" data-kiriof-setup-prev style="width:28px;height:28px;display:inline-flex;align-items:center;justify-content:center;border:0;background:transparent;color:#1d2327;cursor:pointer;padding:0">
                <span class="screen-reader-text"><?php echo esc_html__( 'Previous step', 'kiriminaja-official' ); ?></span>
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M15.41 7.41L14 6l-6 6l6 6l1.41-1.41L10.83 12z"/></svg>
            </button>
            <button type="button" class="button-link" data-kiriof-setup-next style="width:28px;height:28px;display:inline-flex;align-items:center;justify-content:center;border:0;background:transparent;color:#1d2327;cursor:pointer;padding:0">
                <span class="screen-reader-text"><?php echo esc_html__( 'Next step', 'kiriminaja-official' ); ?></span>
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="m10 6l-1.41 1.41L13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
            </button>
        </div>
    </div>
    <div style="padding:20px;background:#fff">
        <?php foreach ( $steps as $kiriof_index => $kiriof_step ) : ?>
        <section data-kiriof-setup-slide style="<?php echo $kiriof_index === $kiriof_current_index ? '' : 'display:none;'; ?>">
            <div style="display:inline-flex;align-items:center;gap:10px;padding:10px 16px;border:1px solid #d0d4da;border-radius:999px;font-size:14px;font-weight:500;color:#1d2327;margin-bottom:18px">
                <?php if ( $kiriof_step['done'] ) : ?>
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" style="color:#5c2ecb;flex-shrink:0" aria-hidden="true"><path fill="currentColor" fill-rule="evenodd" d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2m4.293 7.293a1 1 0 0 1 0 1.414l-5 5a1 1 0 0 1-1.414 0l-2-2a1 1 0 0 1 1.414-1.414l1.293 1.293l4.293-4.293a1 1 0 0 1 1.414 0" clip-rule="evenodd"/></svg>
                <?php else : ?>
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" style="color:#5c2ecb;flex-shrink:0" aria-hidden="true"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12h-3a7 7 0 1 1-7-7z"/></svg>
                <?php endif; ?>
                <span>
                    <?php
                    if ( $kiriof_index === $kiriof_total_steps - 1 && ! $kiriof_step['required'] ) {
                        echo esc_html__( 'Tahap Terakhir', 'kiriminaja-official' );
                    } else {
                        printf(
                            esc_html__( 'Tahap %1$d dari %2$d', 'kiriminaja-official' ),
                            absint( $kiriof_index + 1 ),
                            absint( $kiriof_total_steps )
                        );
                    }
                    ?>
                </span>
            </div>
            <div style="max-width:920px">
                <div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap;margin-bottom:10px">
                    <h2 style="margin:0;font-size:22px;line-height:1.25;font-weight:700;color:#111"><?php echo esc_html( $kiriof_step['title'] ); ?></h2>
                    <?php if ( ! $kiriof_step['required'] ) : ?>
                    <span style="font-size:14px;font-weight:500;color:#50575e"><?php echo esc_html__( 'Opsional', 'kiriminaja-official' ); ?></span>
                    <?php endif; ?>
                </div>
                <p style="margin:0 0 20px;font-size:15px;line-height:1.5;color:#1d2327;max-width:760px"><?php echo esc_html( $kiriof_step['description'] ); ?></p>
                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                    <a href="<?php echo esc_url( $kiriof_step['url'] ); ?>" class="button button-primary" style="margin:0;background:#5c2ecb;border-color:#5c2ecb;min-height:40px;padding:0 14px;display:inline-flex;align-items:center;gap:8px">
                        <span class="dashicons dashicons-admin-generic" aria-hidden="true" style="font-size:16px;width:16px;height:16px"></span>
                        <span><?php echo esc_html( $kiriof_step['done'] ? __( 'Lihat Pengaturan', 'kiriminaja-official' ) : __( 'Konfigurasi Sekarang', 'kiriminaja-official' ) ); ?></span>
                    </a>
                    <?php if ( ! $kiriof_step['required'] ) : ?>
                    <button type="button" class="button" data-kiriof-setup-skip style="min-height:40px;padding:0 16px;border-color:#c3c4c7;color:#3858e9;background:#fff">
                        <?php echo esc_html__( 'Lewati', 'kiriminaja-official' ); ?>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <?php endforeach; ?>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var root = document.getElementById('<?php echo esc_js( $kiriof_notice_id ); ?>');
    if (!root) {
        return;
    }

    var slides = Array.prototype.slice.call(root.querySelectorAll('[data-kiriof-setup-slide]'));
    if (!slides.length) {
        return;
    }

    var currentIndex = <?php echo (int) $kiriof_current_index; ?>;
    var prevButton = root.querySelector('[data-kiriof-setup-prev]');
    var nextButton = root.querySelector('[data-kiriof-setup-next]');

    function bindSkipButton() {
        var skipButton = slides[currentIndex].querySelector('[data-kiriof-setup-skip]');
        if (!skipButton) {
            return;
        }

        skipButton.onclick = function () {
            if (currentIndex < slides.length - 1) {
                renderSlide(currentIndex + 1);
            }
        };
    }

    function renderSlide(index) {
        currentIndex = index;

        slides.forEach(function (slide, slideIndex) {
            slide.style.display = slideIndex === currentIndex ? 'block' : 'none';
        });

        if (prevButton) {
            prevButton.disabled = currentIndex === 0;
            prevButton.style.opacity = currentIndex === 0 ? '0.35' : '1';
            prevButton.style.cursor = currentIndex === 0 ? 'default' : 'pointer';
        }

        if (nextButton) {
            nextButton.disabled = currentIndex === slides.length - 1;
            nextButton.style.opacity = currentIndex === slides.length - 1 ? '0.35' : '1';
            nextButton.style.cursor = currentIndex === slides.length - 1 ? 'default' : 'pointer';
        }

        bindSkipButton();
    }

    if (prevButton) {
        prevButton.addEventListener('click', function () {
            if (currentIndex > 0) {
                renderSlide(currentIndex - 1);
            }
        });
    }

    if (nextButton) {
        nextButton.addEventListener('click', function () {
            if (currentIndex < slides.length - 1) {
                renderSlide(currentIndex + 1);
            }
        });
    }

    renderSlide(currentIndex);
});
</script>
