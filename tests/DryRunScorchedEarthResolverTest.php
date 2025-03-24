<?php declare(strict_types=1);

namespace Cspray\ScorchedEarthGitConflictResolver\Test;

use Cspray\ScorchedEarthGitConflictResolver\ConflictType;
use Cspray\ScorchedEarthGitConflictResolver\DryRunScorchedEarthResolver;
use Cspray\ScorchedEarthGitConflictResolver\GitRepository;
use Cspray\ScorchedEarthGitConflictResolver\GitStatusParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

#[CoversClass(DryRunScorchedEarthResolver::class)]
final class DryRunScorchedEarthResolverTest extends TestCase {

    public const string FOUND_FILES_FOOTER_TEXT = <<<TEXT
    If these operations DO appear valid, execute this command without the --dry-run flag to perform actual operations
    on the filesystem. NOTE: Performing this command without this flag will perform destructive operations!
    
    If these operations DO NOT appear valid, resolve any errant conflicts manually. It is important that resolution of
    this conflict results in the offending file no longer appearing in the output of `git status`.
    TEXT;


    public function testNoConflictingFilesOutputsThatNoOperationsPerformed() : void {
        $output = new BufferedOutput();
        $subject = new DryRunScorchedEarthResolver($output);

        $status = '';
        $repo = $this->createMock(GitRepository::class);
        $repo->expects($this->once())
            ->method('path')
            ->willReturn('/path/to/repo');
        $repo->expects($this->once())
            ->method('status')
            ->willReturn($status);
        $subject->resolve(new GitStatusParser($repo, '/path/to/clean'));

        self::assertSame(
            "No conflicting files found! No operations would be performed.\n",
            $output->fetch()
        );
    }

    public function testBothModifiedFileOutputsThatPathFromCleanWillBeWrittenIntoRepoAndAddedToTrackedFiles() : void {
        $output = new BufferedOutput();
        $subject = new DryRunScorchedEarthResolver($output);

        $status = 'UU relative/path/to/conflicting/file';
        $repo = $this->createMock(GitRepository::class);
        $repo->expects($this->once())
            ->method('path')
            ->willReturn('/path/to/repo');
        $repo->expects($this->once())
            ->method('status')
            ->willReturn($status);
        $subject->resolve(new GitStatusParser($repo, '/path/to/clean'));

        $footer = self::FOUND_FILES_FOOTER_TEXT;
        $expected = <<<TEXT
        Found 1 conflicting file.

        COPY /path/to/clean/relative/path/to/conflicting/file TO /path/to/repo/relative/path/to/conflicting/file
        GIT ADD /path/to/repo/relative/path/to/conflicting/file

        $footer\n
        TEXT;

        self::assertSame($expected, $output->fetch());
    }

    public static function deletedFilesProvider() : array {
        return [
            ['DU', ConflictType::DeletedByUs],
            ['UD', ConflictType::DeletedByThem],
            ['DD', ConflictType::BothDeleted],
        ];
    }

    #[DataProvider('deletedFilesProvider')]
    public function testDeletedFilesAreMarkedAsRemoved(
        string $status,
        ConflictType $conflictType
    ) : void {
        $output = new BufferedOutput();
        $subject = new DryRunScorchedEarthResolver($output);

        $status = $status . ' relative/path/to/conflicting/file';
        $repo = $this->createMock(GitRepository::class);
        $repo->expects($this->once())
            ->method('path')
            ->willReturn('/path/to/repo');
        $repo->expects($this->once())
            ->method('status')
            ->willReturn($status);
        $subject->resolve(new GitStatusParser($repo, '/path/to/clean'));

        $footer = self::FOUND_FILES_FOOTER_TEXT;
        $expected = <<<TEXT
        Found 1 conflicting file.

        ($conflictType->name) GIT RM /path/to/repo/relative/path/to/conflicting/file

        $footer\n
        TEXT;

        self::assertSame($expected, $output->fetch());
    }

    public function testHandlesOutputWithMultipleConflictingFiles() : void {
        $output = new BufferedOutput();
        $subject = new DryRunScorchedEarthResolver($output);

        $status = <<<TEXT
UU relative/path/to/conflicting/file
UU another/conflicting-file
DU deleted-by-us/file
UD deleted-by-them/file
DD deleted-by-both/file
TEXT;

        $repo = $this->createMock(GitRepository::class);
        $repo->expects($this->once())
            ->method('path')
            ->willReturn('/path/to/repo');
        $repo->expects($this->once())
            ->method('status')
            ->willReturn($status);
        $subject->resolve(new GitStatusParser($repo, '/path/to/clean'));

        $footer = self::FOUND_FILES_FOOTER_TEXT;
        $expected = <<<TEXT
        Found 5 conflicting files.

        COPY /path/to/clean/relative/path/to/conflicting/file TO /path/to/repo/relative/path/to/conflicting/file
        GIT ADD /path/to/repo/relative/path/to/conflicting/file
        COPY /path/to/clean/another/conflicting-file TO /path/to/repo/another/conflicting-file
        GIT ADD /path/to/repo/another/conflicting-file
        (DeletedByUs) GIT RM /path/to/repo/deleted-by-us/file
        (DeletedByThem) GIT RM /path/to/repo/deleted-by-them/file
        (BothDeleted) GIT RM /path/to/repo/deleted-by-both/file

        $footer\n
        TEXT;

        self::assertSame($expected, $output->fetch());
    }

}