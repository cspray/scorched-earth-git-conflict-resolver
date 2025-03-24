<?php declare(strict_types=1);

namespace Cspray\ScorchedEarthGitConflictResolver\Test;

use Cspray\ScorchedEarthGitConflictResolver\ConflictType;
use Cspray\ScorchedEarthGitConflictResolver\DestructiveOperationsPerformedScorchedEarthResolver;
use Cspray\ScorchedEarthGitConflictResolver\GitRepository;
use Cspray\ScorchedEarthGitConflictResolver\GitStatusParser;
use org\bovigo\vfs\vfsStreamDirectory as VirtualDirectory;
use org\bovigo\vfs\vfsStream as VirtualFilesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

#[CoversClass(DestructiveOperationsPerformedScorchedEarthResolver::class)]
final class DestructiveOperationsPerformedScorchedEarthResolverTest extends TestCase {

    private GitRepository&MockObject $git;
    private VirtualDirectory $vfs;

    protected function setUp() : void {
        $this->vfs = VirtualFilesystem::setup();
        $this->vfs->addChild(VirtualFilesystem::newDirectory('dirty'));
        $this->vfs->addChild(VirtualFilesystem::newDirectory('clean'));
        $this->git = $this->createMock(GitRepository::class);
    }

    public function testStatusWithModifiedFilesWouldCopyContentsFromCleanToDirtyAndAddDirtyPathToGitRepository() : void {
        $this->vfs->addChild(
            VirtualFilesystem::newFile('dirty/conflicting-file')->withContent('this is dirty content')
        );
        $this->vfs->addChild(
            VirtualFilesystem::newFile('clean/conflicting-file')->withContent('this is clean content')
        );

        $this->git->expects($this->once())
            ->method('path')
            ->willReturn('vfs://root/dirty');
        $this->git->expects($this->once())
            ->method('status')
            ->willReturn('UU conflicting-file');
        $this->git->expects($this->once())
            ->method('add')
            ->with('vfs://root/dirty/conflicting-file');

        $output = new BufferedOutput();
        $subject = new DestructiveOperationsPerformedScorchedEarthResolver($this->git, $output);
        $subject->resolve(new GitStatusParser($this->git, 'vfs://root/clean'));

        self::assertSame(
            'this is clean content',
            $this->vfs->getChild('dirty/conflicting-file')->getContent()
        );
        $expected = <<<TEXT
Found 1 conflicting file.

COPY vfs://root/clean/conflicting-file TO vfs://root/dirty/conflicting-file
GIT ADD vfs://root/dirty/conflicting-file

Please review your repository and continue/abort your rebase, cherry-pick, or merge.\n
TEXT;
        self::assertSame($expected, $output->fetch());
    }

    public static function deletedConflictTypeProvider() : array {
        return [
            ['DU', ConflictType::DeletedByUs],
            ['UD', ConflictType::DeletedByThem],
            ['DD', ConflictType::BothDeleted]
        ];
    }

    #[DataProvider('deletedConflictTypeProvider')]
    public function testDeletedFileIsRemovedFromGitRepository(string $status, ConflictType $conflictType) : void {
        $this->vfs->addChild(
            VirtualFilesystem::newFile('dirty/conflicting-file')->withContent('this is dirty content')
        );
        // we don't expect a deleted file to be available in the clean content

        $this->git->expects($this->once())
            ->method('path')
            ->willReturn('vfs://root/dirty');
        $this->git->expects($this->once())
            ->method('status')
            ->willReturn($status . ' conflicting-file');
        $this->git->expects($this->once())
            ->method('remove')
            ->with('vfs://root/dirty/conflicting-file');

        $output = new BufferedOutput();
        $subject = new DestructiveOperationsPerformedScorchedEarthResolver($this->git, $output);
        $subject->resolve(new GitStatusParser($this->git, 'vfs://root/clean'));

        $expected = <<<TEXT
Found 1 conflicting file.

($conflictType->name) GIT RM vfs://root/dirty/conflicting-file

Please review your repository and continue/abort your rebase, cherry-pick, or merge.\n
TEXT;
        self::assertSame($expected, $output->fetch());
    }

}