<?php declare(strict_types=1);

namespace Cspray\ScorchedEarthGitConflictResolver\Command;

use Cspray\ScorchedEarthGitConflictResolver\DestructiveOperationsPerformedScorchedEarthResolver;
use Cspray\ScorchedEarthGitConflictResolver\DryRunScorchedEarthResolver;
use Cspray\ScorchedEarthGitConflictResolver\GitRepositoryFactory;
use Cspray\ScorchedEarthGitConflictResolver\GitStatusParser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

final class ResolveCommand extends Command {

    public function __construct(
        private readonly GitRepositoryFactory $repositoryFactory
    ) {
        parent::__construct();

    }

    protected function configure() : void {
        $this->setName('resolve');
        $this->addArgument(
            'repoDirectory',
            InputArgument::REQUIRED,
            'The path to the Git repository that has conflicting files'
        );
        $this->addArgument(
            'cleanDirectory',
            InputArgument::REQUIRED,
            'The path to the clean directory holding files to use for resolving conflicts in repoDirectory.'
        );
        $this->addOption(
            'dry-run',
            mode: InputOption::VALUE_NONE,
            description: 'Pass this flag to enable a dry-run, the command will output what it WOULD do in a real run.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int {
        $dryRun = $input->getOption('dry-run');
        if (!$dryRun) {
            $confirmation = new ConfirmationQuestion('Are you sure you want to continue?', false);
            if (!$this->getHelper('question')->ask($input, $output, $confirmation)) {
                $output->writeln('No operations performed!');
                return self::SUCCESS;
            }
        }

        $repository = $this->repositoryFactory->gitRepository($input->getArgument('repoDirectory'));
        $resolver = $dryRun
            ? new DryRunScorchedEarthResolver($output)
            : new DestructiveOperationsPerformedScorchedEarthResolver($repository, $output);
        $statusParser = new GitStatusParser($repository, $input->getArgument('cleanDirectory'));

        $resolver->resolve($statusParser);

        return self::SUCCESS;
    }

}