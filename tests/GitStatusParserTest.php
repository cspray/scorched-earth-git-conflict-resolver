<?php declare(strict_types=1);

namespace Cspray\ScorchedEarthGitConflictResolver\Test;

use Cspray\ScorchedEarthGitConflictResolver\ConflictType;
use Cspray\ScorchedEarthGitConflictResolver\GitRepository;
use Cspray\ScorchedEarthGitConflictResolver\GitStatusParser;
use Cspray\ScorchedEarthGitConflictResolver\ScorchedEarthFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStreamDirectory as VirtualDirectory;
use org\bovigo\vfs\vfsStream as VirtualFilesystem;

#[CoversClass(GitStatusParser::class)]
#[UsesClass(GitRepository::class)]
final class GitStatusParserTest extends TestCase {

    private GitRepository&MockObject $git;
    private VirtualDirectory $vfs;

    protected function setUp() : void {
        $this->vfs = VirtualFilesystem::setup();
        $this->vfs->addChild(VirtualFilesystem::newDirectory('dirty'));
        $this->vfs->addChild(VirtualFilesystem::newDirectory('clean'));
        $this->git = $this->createMock(GitRepository::class);
    }

    public function testNoContentYieldsEmptyGenerator() : void {
        $this->git->expects($this->once())
            ->method('path')
            ->willReturn('vfs://root/dirty');
        $this->git->expects($this->once())
            ->method('status')
            ->willReturn('');
        $subject = new GitStatusParser($this->git, 'vfs://root/clean');

        $actual = iterator_to_array($subject->parse());
        self::assertSame([], $actual);
    }

    public function testContentWithNoUnmergedFilesYieldsEmptyGenerator() : void {
        $status = <<<GIT
M  path/to/modified
A  path/to/added
R  path/to/renamed
C  path/to/copied
T  path/to/type
GIT;
        $this->git->expects($this->once())
            ->method('path')
            ->willReturn('vfs://root/dirty');
        $this->git->expects($this->once())
            ->method('status')
            ->willReturn($status);

        $subject = new GitStatusParser($this->git, 'vfs://root/clean');
        $actual = iterator_to_array($subject->parse());
        self::assertSame([], $actual);
    }

    public function testContentWithBothModifiedFilesIncludesCorrectScorchedEarthFiles() : void {
        $status = <<<TEXT
M  path/to/modified
M  path/to/modified-two
UU path/to/both-modified
M  path/to/modified-three
UU path/to/both-modified-two
TEXT;

        $this->git->expects($this->once())
            ->method('status')
            ->willReturn($status);
        $this->git->expects($this->once())
            ->method('path')
            ->willReturn('vfs://root/dirty');
        $subject = new GitStatusParser($this->git, 'vfs://root/clean');

        $actual = iterator_to_array($subject->parse());

        self::assertCount(2, $actual);
        self::assertContainsOnlyInstancesOf(ScorchedEarthFile::class, $actual);

        $bothModified = $actual[0];
        self::assertSame(
            'vfs://root/dirty/path/to/both-modified',
            $bothModified->dirtyPath()
        );
        self::assertSame(
            'vfs://root/clean/path/to/both-modified',
            $bothModified->cleanPath()
        );
        self::assertSame(
            ConflictType::BothModified,
            $bothModified->conflictType()
        );

        $bothModifiedTwo = $actual[1];
        self::assertSame(
            'vfs://root/dirty/path/to/both-modified-two',
            $bothModifiedTwo->dirtyPath()
        );
        self::assertSame(
            'vfs://root/clean/path/to/both-modified-two',
            $bothModifiedTwo->cleanPath()
        );
        self::assertSame(
            ConflictType::BothModified,
            $bothModifiedTwo->conflictType()
        );
    }

    public function testContentWithFilesDeletedByUsAreIncluded() : void {
        $status = <<<TEXT
UU path/to/diff-modified
M  path/to/modified
DU path/to/deleted-by-us
M  path/to/modified-two
M  path/to/modified-three
TEXT;

        $this->git->expects($this->once())
            ->method('status')
            ->willReturn($status);
        $this->git->expects($this->once())
            ->method('path')
            ->willReturn('vfs://root/dirty');
        $subject = new GitStatusParser($this->git, 'vfs://root/clean');

        $actual = iterator_to_array($subject->parse());

        self::assertCount(2, $actual);
        self::assertContainsOnlyInstancesOf(ScorchedEarthFile::class, $actual);

        $bothModified = $actual[0];
        self::assertSame(
            'vfs://root/dirty/path/to/diff-modified',
            $bothModified->dirtyPath()
        );
        self::assertSame(
            'vfs://root/clean/path/to/diff-modified',
            $bothModified->cleanPath()
        );
        self::assertSame(
            ConflictType::BothModified,
            $bothModified->conflictType()
        );

        $deletedByUs = $actual[1];
        self::assertSame(
            'vfs://root/dirty/path/to/deleted-by-us',
            $deletedByUs->dirtyPath()
        );
        self::assertNull(
            $deletedByUs->cleanPath()
        );
        self::assertSame(
            ConflictType::DeletedByUs,
            $deletedByUs->conflictType()
        );
    }

    public function testContentWithFilesDeletedByThemIncludesCorrectCollection() : void {
        $status = <<<TEXT
UU path/to/diff-modified
M  path/to/modified
DU path/to/deleted-by-us
M  path/to/modified-two
UD path/to/deleted-by-them
M  path/to/modified-three
TEXT;

        $this->git->expects($this->once())
            ->method('status')
            ->willReturn($status);
        $this->git->expects($this->once())
            ->method('path')
            ->willReturn('vfs://root/dirty');
        $subject = new GitStatusParser($this->git, 'vfs://root/clean');

        $actual = iterator_to_array($subject->parse());
        self::assertCount(3, $actual);

        $bothModified = $actual[0];
        self::assertSame(
            'vfs://root/dirty/path/to/diff-modified',
            $bothModified->dirtyPath()
        );
        self::assertSame(
            'vfs://root/clean/path/to/diff-modified',
            $bothModified->cleanPath()
        );
        self::assertSame(
            ConflictType::BothModified,
            $bothModified->conflictType()
        );

        $deletedByUs = $actual[1];
        self::assertSame(
            'vfs://root/dirty/path/to/deleted-by-us',
            $deletedByUs->dirtyPath()
        );
        self::assertNull(
            $deletedByUs->cleanPath()
        );
        self::assertSame(
            ConflictType::DeletedByUs,
            $deletedByUs->conflictType()
        );

        $deletedByThem = $actual[2];
        self::assertSame(
            'vfs://root/dirty/path/to/deleted-by-them',
            $deletedByThem->dirtyPath()
        );
        self::assertNull(
            $deletedByThem->cleanPath()
        );
        self::assertSame(
            ConflictType::DeletedByThem,
            $deletedByThem->conflictType()
        );
    }

    public function testContentWithFilesDeletedByBothIncludesCorrectCollection() : void {
        $status = <<<TEXT
UU path/to/diff-modified
M  path/to/modified
M  path/to/modified-two
DD path/to/deleted-by-both
M  path/to/modified-three
TEXT;

        $this->git->expects($this->once())
            ->method('status')
            ->willReturn($status);
        $this->git->expects($this->once())
            ->method('path')
            ->willReturn('vfs://root/dirty');
        $subject = new GitStatusParser($this->git, 'vfs://root/clean');

        $actual = iterator_to_array($subject->parse());
        self::assertCount(2, $actual);

        $bothModified = $actual[0];
        self::assertSame(
            'vfs://root/dirty/path/to/diff-modified',
            $bothModified->dirtyPath()
        );
        self::assertSame(
            'vfs://root/clean/path/to/diff-modified',
            $bothModified->cleanPath()
        );
        self::assertSame(
            ConflictType::BothModified,
            $bothModified->conflictType()
        );

        $deletedByUs = $actual[1];
        self::assertSame(
            'vfs://root/dirty/path/to/deleted-by-both',
            $deletedByUs->dirtyPath()
        );
        self::assertNull(
            $deletedByUs->cleanPath()
        );
        self::assertSame(
            ConflictType::BothDeleted,
            $deletedByUs->conflictType()
        );
    }

}
