<?php

namespace Tests\Feature\Interface;

use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The browser can only check what the markup tells it, and the markup is
 * generated from the server's own rules. These hold that arrangement in place:
 * a form added later without opting in, or a confirmation that does not say
 * what it will do, fails here rather than passing quietly.
 */
class FormMarkupTest extends TestCase
{
    /** @return array<int, string> */
    private function views(): array
    {
        $found = [];

        $walk = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views/livewire'))
        );

        foreach ($walk as $file) {
            if (str_ends_with($file->getPathname(), '.blade.php')) {
                $found[] = $file->getPathname();
            }
        }

        return $found;
    }

    public function test_every_form_asks_the_browser_to_check_it_first(): void
    {
        $missing = [];

        foreach ($this->views() as $path) {
            preg_match_all('/<form\b[^>]*>/s', file_get_contents($path), $tags);

            foreach ($tags[0] ?? [] as $tag) {
                if (str_contains($tag, 'wire:submit') && ! str_contains($tag, 'data-validate')) {
                    $missing[] = basename($path);
                }
            }
        }

        $this->assertSame([], array_values(array_unique($missing)),
            'These forms submit without being checked in the browser first.');
    }

    public function test_no_form_is_left_to_the_browsers_own_validation_bubbles(): void
    {
        // novalidate is set from JavaScript rather than in the markup, so a
        // page whose script fails to load keeps the browser's own checks
        // instead of having none. Assert the arrangement, not the attribute.
        $script = file_get_contents(resource_path('js/ui/validation.js'));

        $this->assertStringContainsString('form[data-validate]:not([novalidate])', $script);
        $this->assertStringContainsString('noValidate = true', $script);
    }

    public function test_every_confirmation_says_what_will_happen(): void
    {
        foreach ($this->views() as $path) {
            $source = file_get_contents($path);
            $asked = substr_count($source, 'data-confirm="');

            if ($asked === 0) {
                continue;
            }

            $this->assertSame($asked, substr_count($source, 'data-confirm-detail='),
                basename($path).' has a confirmation that does not say what will happen.');

            preg_match_all('/data-confirm="([^"]*)"/', $source, $questions);

            foreach ($questions[1] as $question) {
                // "Are you sure?" tells nobody anything; it has to name the
                // thing it is about.
                $this->assertFalse(
                    Str::of($question)->lower()->is(['are you sure?', 'confirm?', 'ok?', 'continue?']),
                    basename($path).' asks a question that names nothing: '.$question,
                );
            }
        }
    }

    public function test_nothing_destructive_falls_back_to_the_browsers_confirm_box(): void
    {
        foreach ($this->views() as $path) {
            $this->assertStringNotContainsString('wire:confirm', file_get_contents($path),
                basename($path).' still uses the browser confirm box.');
        }
    }

    /**
     * Every view compiles.
     *
     * A malformed tag is invisible until somebody opens that one screen, and
     * a broken screen in production is a worse way to find out than a red
     * test. This compiles the lot in a second.
     */
    public function test_every_view_compiles(): void
    {
        $compiler = app('blade.compiler');
        $broken = [];

        foreach ($this->views() as $path) {
            $file = tempnam(sys_get_temp_dir(), 'blade').'.php';

            file_put_contents($file, $compiler->compileString(file_get_contents($path)));
            $result = shell_exec('php -l '.escapeshellarg($file).' 2>&1');
            @unlink($file);

            if (! str_contains((string) $result, 'No syntax errors')) {
                $broken[] = basename($path).': '.trim(explode("\n", (string) $result)[0]);
            }
        }

        $this->assertSame([], $broken);
    }

    public function test_no_em_dashes_are_left_in_the_interface(): void
    {
        $offenders = [];

        foreach ($this->views() as $path) {
            $source = file_get_contents($path);

            if (str_contains($source, "\u{2014}") || str_contains($source, '&mdash;')) {
                $offenders[] = basename($path);
            }
        }

        $this->assertSame([], $offenders);
    }
}
