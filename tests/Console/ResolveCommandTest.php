<?php declare(strict_types=1);

namespace Cspray\ScorchedEarthGitConflictResolver\Test\Console;

use Cspray\ScorchedEarthGitConflictResolver\Command\ResolveCommand;
use Cspray\ScorchedEarthGitConflictResolver\GitRepository;
use Cspray\ScorchedEarthGitConflictResolver\GitRepositoryFactory;
use Cspray\ScorchedEarthGitConflictResolver\Test\DryRunScorchedEarthResolverTest;
use org\bovigo\vfs\vfsStream as VirtualFilesystem;
use org\bovigo\vfs\vfsStreamDirectory as VirtualDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(ResolveCommand::class)]
class ResolveCommandTest extends TestCase {

    private GitRepositoryFactory&MockObject $repositoryFactory;
    private VirtualDirectory $vfs;

    protected function setUp() : void {
        $this->repositoryFactory = $this->createMock(GitRepositoryFactory::class);
        $this->vfs = VirtualFilesystem::setup();
        $this->vfs->addChild(VirtualFilesystem::newDirectory('dirty'));
        $this->vfs->addChild(VirtualFilesystem::newDirectory('clean'));
    }

    public function testResolveCommandHasCorrectName() : void {
        $subject = new ResolveCommand($this->repositoryFactory);
        self::assertSame('resolve', $subject->getName());
    }

    public function testResolveCommandHasCorrectArguments() : void {
        $subject = new ResolveCommand($this->repositoryFactory);
        self::assertSame(2, $subject->getDefinition()->getArgumentCount());
        self::assertTrue($subject->getDefinition()->getArgument('repoDirectory')->isRequired());
        self::assertTrue($subject->getDefinition()->getArgument('cleanDirectory')->isRequired());
    }

    public function testResolveCommandHasCorrectOptions() : void {
        $subject = new ResolveCommand($this->repositoryFactory);
        self::assertCount(1, $subject->getDefinition()->getOptions());
        self::assertFalse($subject->getDefinition()->getOption('dry-run')->acceptValue());
    }

    public function testDryRunFlagProvidedRunsCorrectResolverWithProvidedInputArguments() : void {
        $commandTester = new CommandTester(new ResolveCommand($this->repositoryFactory));
        $this->vfs->addChild(VirtualFilesystem::newFile('dirty/path/to/conflicting/file')->withContent('dirty content'));
        $this->vfs->addChild(VirtualFilesystem::newFile('clean/path/to/conflicting/file')->withContent('clean content'));

        $gitRepo = $this->createMock(GitRepository::class);
        $gitRepo->expects($this->once())
            ->method('path')
            ->willReturn('vfs://root/dirty');

        $gitRepo->expects($this->once())
            ->method('status')
            ->willReturn('UU path/to/conflicting/file');

        $gitRepo->expects($this->never())->method('add');
        $gitRepo->expects($this->never())->method('remove');

        $this->repositoryFactory->expects($this->once())
            ->method('gitRepository')
            ->willReturn($gitRepo);

        $commandTester->execute([
            'repoDirectory' => 'vfs://root/dirty',
            'cleanDirectory' => 'vfs://root/clean',
            '--dry-run' => true
        ]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());

        $footer = DryRunScorchedEarthResolverTest::FOUND_FILES_FOOTER_TEXT;
        $expectedOutput = <<<TEXT
        Found 1 conflicting file.
        
        COPY vfs://root/clean/path/to/conflicting/file TO vfs://root/dirty/path/to/conflicting/file
        GIT ADD vfs://root/dirty/path/to/conflicting/file
        
        $footer\n
        TEXT;

        self::assertSame($expectedOutput, $commandTester->getDisplay());
        self::assertSame('dirty content', $this->vfs->getChild('dirty/path/to/conflicting/file')->getContent());
    }

    public function testWithoutDryRunFlagUserPromptedForConfirmationAndOutputsCorrectInfoIfToldNo() : void {
        $command = new ResolveCommand($this->repositoryFactory);
        $command->setHelperSet(new HelperSet([
            new QuestionHelper()
        ]));
        $commandTester = new CommandTester($command);
        $this->vfs->addChild(VirtualFilesystem::newFile('dirty/path/to/conflicting/file')->withContent('dirty content'));
        $this->vfs->addChild(VirtualFilesystem::newFile('clean/path/to/conflicting/file')->withContent('clean content'));

        $this->repositoryFactory->expects($this->never())->method('gitRepository');

        $commandTester->setInputs(['no']);
        $commandTester->execute([
            'repoDirectory' => 'vfs://root/dirty',
            'cleanDirectory' => 'vfs://root/clean',
        ]);

        $expected = <<<TEXT
        Are you sure you want to continue?No operations performed!\n
        TEXT;

        self::assertSame($expected, $commandTester->getDisplay());
        self::assertSame('dirty content', $this->vfs->getChild('dirty/path/to/conflicting/file')->getContent());
    }

    public function testWithoutDryRunFlagUserPromptedForConfirmationAndPerformsDestructiveOperationsIfToldYes() : void {
        $command = new ResolveCommand($this->repositoryFactory);
        $command->setHelperSet(new HelperSet([
            new QuestionHelper()
        ]));
        $commandTester = new CommandTester($command);
        $this->vfs->addChild(VirtualFilesystem::newFile('dirty/path/to/conflicting/file')->withContent('dirty content'));
        $this->vfs->addChild(VirtualFilesystem::newFile('clean/path/to/conflicting/file')->withContent('clean content'));

        $gitRepo = $this->createMock(GitRepository::class);
        $gitRepo->expects($this->once())
            ->method('path')
            ->willReturn('vfs://root/dirty');
        $gitRepo->expects($this->once())
            ->method('status')
            ->willReturn('UU path/to/conflicting/file');
        $gitRepo->expects($this->once())
            ->method('add')
            ->with('vfs://root/dirty/path/to/conflicting/file');
        $gitRepo->expects($this->never())->method('remove');

        $this->repositoryFactory->expects($this->once())
            ->method('gitRepository')
            ->willReturn($gitRepo);

        $commandTester->setInputs(['yes']);
        $commandTester->execute([
            'repoDirectory' => 'vfs://root/dirty',
            'cleanDirectory' => 'vfs://root/clean',
        ]);

        $expected = <<<TEXT
        Are you sure you want to continue?Found 1 conflicting file.
        
        COPY vfs://root/clean/path/to/conflicting/file TO vfs://root/dirty/path/to/conflicting/file
        GIT ADD vfs://root/dirty/path/to/conflicting/file
        
        Please review your repository and continue/abort your rebase, cherry-pick, or merge.\n
        TEXT;

        self::assertSame($expected, $commandTester->getDisplay());
        self::assertSame('clean content', $this->vfs->getChild('dirty/path/to/conflicting/file')->getContent());
    }

}