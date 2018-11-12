<?php

namespace App\Command;


use App\Service\ImageService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SortImagesCommand extends Command
{

    private $imageService;

    public function __construct(ImageService $imageService)
    {
        $this->imageService = $imageService;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('app:sort-images');
        $this->setDescription('Sorts images');
        $this->setHelp('This command sorts images by mont of creation');

        $this->addArgument('year', InputArgument::REQUIRED, 'The year of the image.');
        $this->addArgument('source', InputArgument::REQUIRED, 'The path of the source folder.');
        $this->addArgument('target', InputArgument::REQUIRED, 'The path of the target folder.');

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->imageService->setYear($input->getArgument('year'));
        $this->imageService->setSource($input->getArgument('source'));
        $this->imageService->setTarget($input->getArgument('target'));

        $output->writeln([
            'Sort your images by year and month.',
            '===================================',
            ''
        ]);


        $output->writeln([
            'Configuration',
            '-------------',
            'Year: ' . $input->getArgument('year'),
            'Source: ' . $input->getArgument('source'),
            'Target: ' . $input->getArgument('target'),
            ''
        ]);

        $progressBar = new ProgressBar($output,10);
        $progressBar->start();

        $this->imageService->sortImages();

        $i = 1;
        while ($i < 10) {
            $i++;
            sleep(1);
            $progressBar->advance();
        }
        $progressBar->finish();

        $output->writeln(['', '']);
        $output->writeln([
            'Result',
            '------',
            'Analyzed images: ' . $this->imageService->getFileCount(),
            'Succeeded images: ' . count($this->imageService->getSucceededFiles()),
            ''
        ]);
    }

}
