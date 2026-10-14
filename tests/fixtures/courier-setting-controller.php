<?php
/** Compose courier controller tests across the branch's explicit DI boundary. */
function courier_setting_controller( \KiriminAjaOfficial\Repositories\SettingRepository $repository ): \KiriminAjaOfficial\Controllers\SettingController {
    // Courier endpoints use the real injected settings repository. Tracking is
    // unrelated to these scenarios and must not trigger WordPress queries.
    $tracking = new class implements \KiriminAjaOfficial\Contracts\TrackingPageRepositoryInterface {
        public function hasPublishedTrackingPage(): bool { throw new RuntimeException( 'Unexpected tracking lookup' ); }
        public function findPublishedTrackingContent(): array { throw new RuntimeException( 'Unexpected tracking lookup' ); }
        public function findTrackingShortcodePages(): array { throw new RuntimeException( 'Unexpected tracking lookup' ); }
        public function findPreferredTrackingShortcodePage() { throw new RuntimeException( 'Unexpected tracking lookup' ); }
    };
    return new \KiriminAjaOfficial\Controllers\SettingController( $tracking, $repository );
}
