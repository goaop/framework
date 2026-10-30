<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Console\Warmup;

use PHPUnit\Framework\Attributes\RequiresOperatingSystemFamily;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

final class WarmupReporterTest extends TestCase
{
    private function createStreamOutput(bool $decorated = true): StreamOutput
    {
        $stream = fopen('php://memory', 'w+');
        assert($stream !== false);

        return new StreamOutput($stream, OutputInterface::VERBOSITY_NORMAL, $decorated);
    }

    private static function readStream(StreamOutput $output): string
    {
        rewind($output->getStream());

        return (string) stream_get_contents($output->getStream());
    }

    /**
     * What a terminal shows in the end: the cursor movements of the redraws applied, the colors removed
     */
    private static function renderScreen(string $output): string
    {
        $output = (string) preg_replace('/\e\][^\e]*\e\\\\/', '', $output);
        $lines  = [''];
        $row    = 0;
        $parts  = preg_split('/(\e\[[\d;]*[A-Za-z])/', $output, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        foreach ($parts !== false ? $parts : [] as $part) {
            if (preg_match('/^\e\[([\d;]*)([A-Za-z])$/', $part, $matches) === 1) {
                match ($matches[2]) {
                    'A'     => $row = max(0, $row - ($matches[1] !== '' ? (int) $matches[1] : 1)),
                    'K'     => $lines[$row] = '',
                    default => null,
                };
                continue;
            }
            foreach (explode("\n", $part) as $index => $text) {
                if ($index > 0) {
                    $row++;
                    $lines[$row] ??= '';
                }
                $lines[$row] .= $text;
            }
        }

        return implode("\n", $lines);
    }

    public function testProgressBarIsRedrawnInPlaceWithTheFileAtHand(): void
    {
        $output   = $this->createStreamOutput();
        $reporter = new ProgressBarReporter($output, new TerminalCapabilities(true, true, 100), '/app', 0.0);

        $reporter->start(4, 2);
        $reporter->advance('/app/src/First.php', null);
        $reporter->advance('/app/src/Broken.php', 'Cannot weave <it>');
        $display = self::readStream($output);

        $this->assertStringContainsString("\e[", $display, 'The bar is redrawn with cursor movements');
        $screen = self::renderScreen($display);
        $this->assertStringContainsString('Weaving in 2 parallel worker processes.', $screen);
        $this->assertStringContainsString('[ERR]: /app/src/Broken.php: Cannot weave <it>', $screen);
        $this->assertMatchesRegularExpression('/ 2\/4 \[█+░+]  50%  .*1 errors\n src\/Broken\.php$/u', $screen);

        $reporter->advance('/app/src/Third.php', null);
        $reporter->advance('/app/src/Fourth.php', null);
        $reporter->finish(4, 1);
        $screen = self::renderScreen(self::readStream($output));

        $this->assertMatchesRegularExpression('/ 4\/4 \[█+] 100%  /u', $screen);
        $this->assertStringNotContainsString('Fourth.php', $screen, 'The line of the file at hand is gone');
        $this->assertStringContainsString('[DONE]: Total processed 4, 1 errors in ', $screen);
        $this->assertSame(1, substr_count($screen, '[ERR]'));
    }

    public function testProgressBarFallsBackToAsciiAndShortensLongPaths(): void
    {
        $output   = $this->createStreamOutput();
        $reporter = new ProgressBarReporter($output, new TerminalCapabilities(true, false, 40), '/app', 0.0);

        $reporter->start(2, 1);
        $reporter->advance('/app/src/Some/Very/Deeply/Nested/Directory/WithAVeryLongFileName.php', null);
        $screen = self::renderScreen(self::readStream($output));

        $this->assertStringContainsString('1/2 [', $screen);
        $this->assertStringContainsString('=', $screen);
        $this->assertStringNotContainsString('█', $screen);
        $this->assertStringContainsString(' ...Directory/WithAVeryLongFileName.php', $screen);
        $this->assertStringNotContainsString('Weaving in', $screen, 'No workers, no worker line');
    }

    public function testInterruptedProgressBarIsCleared(): void
    {
        $output   = $this->createStreamOutput();
        $reporter = new ProgressBarReporter($output, new TerminalCapabilities(true, true, 100), null, 0.0);

        $reporter->start(10, 1);
        $reporter->advance('/app/One.php', null);
        $reporter->interrupt();
        $reporter->finish(1, 0);
        $screen = self::renderScreen(self::readStream($output));

        $this->assertStringContainsString('[STOP]: Warmup was interrupted, stopping...', $screen);
        $this->assertStringNotContainsString('1/10', $screen);
        $this->assertStringContainsString('[DONE]: Total processed 1, 0 errors in ', $screen);
    }

    public function testFileListReporterListsEveryFile(): void
    {
        $output   = new BufferedOutput();
        $reporter = new FileListReporter($output);

        $reporter->start(2, 1);
        $reporter->advance('/app/A.php', null);
        $reporter->advance('/app/B.php', 'boom');
        $reporter->finish(2, 1);
        $display = $output->fetch();

        $this->assertStringContainsString('[OK]: /app/A.php', $display);
        $this->assertStringContainsString('[ERR]: /app/B.php: boom', $display);
        $this->assertStringContainsString('[DONE]: Total processed 2, 1 errors in ', $display);
    }

    public function testMilestoneReporterWritesEveryTenthOfTheFiles(): void
    {
        $output   = new BufferedOutput();
        $reporter = new MilestoneReporter($output);

        $reporter->start(100, 4);
        for ($i = 1; $i <= 100; $i++) {
            $reporter->advance("/app/File{$i}.php", $i === 42 ? 'boom' : null);
        }
        $reporter->finish(100, 1);
        $display = $output->fetch();

        $this->assertSame(10, substr_count($display, ' elapsed, '));
        $this->assertStringContainsString(' 10/100 [ 10%]', $display);
        $this->assertStringContainsString(' 50/100 [ 50%]', $display);
        $this->assertStringContainsString('100/100 [100%]', $display);
        $this->assertStringContainsString('files/s, 1 errors', $display);
        $this->assertStringContainsString('[ERR]: /app/File42.php: boom', $display);
        $this->assertStringContainsString('[DONE]: Total processed 100, 1 errors in ', $display);
        $this->assertStringContainsString(' with 4 workers.', $display);
    }

    public function testFactoryListsFilesInVerboseMode(): void
    {
        $output = new BufferedOutput(OutputInterface::VERBOSITY_VERBOSE, true);

        $this->assertInstanceOf(FileListReporter::class, WarmupReporterFactory::create($output));
    }

    /**
     * A decorated output that is no terminal is a CI log with colors: a redrawn bar would leave every redraw behind
     */
    public function testFactoryWritesProgressLinesWhenTheErrorOutputIsNoTerminal(): void
    {
        $output = new ConsoleOutput(OutputInterface::VERBOSITY_NORMAL, true);
        $output->setErrorOutput($this->createStreamOutput());

        $this->assertInstanceOf(MilestoneReporter::class, WarmupReporterFactory::create($output));
        $this->assertInstanceOf(MilestoneReporter::class, WarmupReporterFactory::create(new BufferedOutput()));
    }

    public function testTerminalOfAMemoryStreamIsNotInteractive(): void
    {
        $terminal = TerminalCapabilities::detect($this->createStreamOutput());

        $this->assertFalse($terminal->interactive);
        $this->assertGreaterThan(0, $terminal->width);
    }

    #[RequiresOperatingSystemFamily('Linux')]
    public function testUnicodeSupportFollowsTheLocale(): void
    {
        $previous = getenv('LC_ALL');
        try {
            putenv('LC_ALL=en_US.UTF-8');
            $this->assertTrue(TerminalCapabilities::detect($this->createStreamOutput())->unicode);
            putenv('LC_ALL=C');
            $this->assertFalse(TerminalCapabilities::detect($this->createStreamOutput())->unicode);
        } finally {
            putenv($previous === false ? 'LC_ALL' : 'LC_ALL=' . $previous);
        }
    }
}
