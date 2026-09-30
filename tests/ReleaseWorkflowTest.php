<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ReleaseWorkflowTest extends TestCase
{
    #[Test]
    public function changelog_uses_first_parent_user_facing_commit_titles(): void
    {
        $changelog = file_get_contents( PLUGIN_DIR . '/scripts/changelog.php' );

        $this->assertStringContainsString( 'git log --first-parent', $changelog );
        $this->assertStringContainsString( "'/^(feat(?:ure)?|fix(?:ing)?|perf)", $changelog );
        $this->assertStringNotContainsString( "'chore'", $changelog );
        $this->assertStringNotContainsString( "'build'", $changelog );
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
