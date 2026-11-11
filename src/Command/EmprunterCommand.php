<?php

namespace App\Command;

use App\Exception\RegleMetierException;
use App\Repository\AdherentRepository;
use App\Repository\ExemplaireRepository;
use App\Service\EmpruntService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:emprunter', description: 'Enregistre un prêt (id adhérent + code-barres)')]
class EmprunterCommand extends Command
{
    public function __construct(
        private AdherentRepository $adherents,
        private ExemplaireRepository $exemplaires,
        private EmpruntService $service,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('adherent', InputArgument::REQUIRED, "Id de l'adhérent")
            ->addArgument('codeBarres', InputArgument::REQUIRED, "Code-barres de l'exemplaire");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $adherent = $this->adherents->find($input->getArgument('adherent'));
        $exemplaire = $this->exemplaires->findOneBy(['codeBarres' => $input->getArgument('codeBarres')]);

        if (!$adherent || !$exemplaire) {
            $io->error('Adhérent ou exemplaire introuvable.');
            return Command::FAILURE;
        }

        try {
            $pret = $this->service->emprunter($adherent, $exemplaire);
        } catch (RegleMetierException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $io->success(sprintf('Prêt enregistré, à rendre le %s.', $pret->getDateEcheance()->format('d/m/Y')));
        return Command::SUCCESS;
    }
}
