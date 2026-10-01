<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ReleaseWorkflowTest extends TestCase
{
    #[Test]
    public function changelog_uses_github_pr_based_release_notes(): void
    {
        $changelog = file_get_contents( PLUGIN_DIR . '/scripts/changelog.php' );

        $this->assertStringContainsString( 'kiriof_generate_release_notes', $changelog );
        $this->assertStringContainsString( "require_once __DIR__ . '/release-notes.php';", $changelog );
        $this->assertStringNotContainsString( 'git log', $changelog );
    }

    #[Test]
    public function github_release_uses_the_same_readme_changelog_entry(): void
    {
        $workflow = file_get_contents( PLUGIN_DIR . '/.github/workflows/release.yml' );
        $makefile = file_get_contents( PLUGIN_DIR . '/Makefile' );

        $this->assertFileExists( PLUGIN_DIR . '/scripts/release-notes.php' );
        $this->assertStringContainsString( 'scripts/release-notes.php', $workflow );
        $this->assertStringContainsString( 'body_path:', $workflow );
        $this->assertStringNotContainsString( 'generate_release_notes: true', $workflow );
        $this->assertStringContainsString( 'scripts/release-notes.php', $makefile );
        $this->assertStringContainsString( '--exclude=release-notes.md', $makefile );
    }
}
