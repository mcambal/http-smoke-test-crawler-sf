<?php

namespace App\Command;

use App\Entity\Simple\CrawlerConfiguration;
use App\Entity\Simple\OutputConfiguration;
use App\Handler\CrawlHandler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class HttpSmokeTestCrawlerCommand extends Command
{
    private $crawlHandler;
    protected static $defaultName = 'run:smoke-test';
    protected static $defaultDescription = 'Find easily broken links in your website';

    public function __construct(CrawlHandler $crawlHandler, string $name = null)
    {
        $this->crawlHandler = $crawlHandler;

        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->addArgument('url', InputArgument::REQUIRED, 'Url')
            ->addOption('output', null, InputOption::VALUE_OPTIONAL, 'Option description', 'stdout')
            ->addOption('respectRobots', null, InputOption::VALUE_NONE, "Respect robots")
            ->addOption('delayBetweenRequests', null, InputOption::VALUE_OPTIONAL, "Delay between requests")
            ->addOption('rejectNoFollowLinks', null, InputOption::VALUE_NONE, "Reject nofollow links")
            ->addOption('userAgent', null, InputOption::VALUE_OPTIONAL, "User agent")
            ->addOption('maxCrawlCount', null, InputOption::VALUE_OPTIONAL, "Max. crawl count")
            ->addOption('maxCrawlDepth', null, InputOption::VALUE_OPTIONAL, "Max. crawl depth")
            ->addOption('maxResponseSize', null, InputOption::VALUE_OPTIONAL, "Max. response size")
            ->addOption('filters', null, InputOption::VALUE_OPTIONAL, "Filters")
            ->addOption('emails', null, InputOption::VALUE_OPTIONAL, "Emails")
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $baseUrl = $input->getArgument('url');
        $filters = $input->getOption('filters');

        $crawlConfiguration = $this->createCrawlConfigurationFromOptions(
            $input->getOption('delayBetweenRequests'),
            (bool)$input->getOption('respectRobots'),
            (bool)$input->getOption('rejectNoFollowLinks'),
            $input->getOption('userAgent'),
            $input->getOption('maxCrawlCount'),
            $input->getOption('maxCrawlDepth'),
            $input->getOption('maxResponseSize')
        );

        $outputConfiguration = new OutputConfiguration(
            $this->createTrimmedArray($input->getOption('output')),
            $this->createTrimmedArray($filters)
        );

        $this->crawlHandler->crawl($baseUrl, $crawlConfiguration, $outputConfiguration);

        if ($input->getOption('emails') !== null) {
            $emailList = $this->createTrimmedArray($input->getOption('emails'));
            $this->sendEmailReport($baseUrl, $emailList, $filters, $crawlConfiguration);
        }

        return Command::SUCCESS;
    }

    /**
     * @param string $baseUrl
     * @param array $emails
     * @param string $filters
     * @param CrawlerConfiguration $crawlerConfiguration
     */
    private function sendEmailReport(string $baseUrl, array $emails, string $filters, CrawlerConfiguration $crawlerConfiguration)
    {
        $emailBody = $this->templateRenderer->make('Email/CrawlingReport', [
            'data' => [
                'baseUrl' => $baseUrl,
                'userAgent' => $crawlerConfiguration->getUserAgent() ?? 'SmokeTestCrawler/1.0',
                'filters' => $filters ?? 'no filters used',
                'maxCrawlCount' => $crawlerConfiguration->getMaximumCrawlCount() ?? 'no limits',
                'maxCrawlDepth' => $crawlerConfiguration->getMaximumCrawlDepth() ?? 'no limits',
                'maxResponseSize' => $crawlerConfiguration->getMaximumResponseSize() ?? 'no limits'
            ]
        ])->render();

        $this->crawlHandler->sendEmailReport(
            'noreply@webcrawler.eset.com',
            'Http Smoke Test Report (' . $baseUrl . ')',
            $emails,
            $emailBody
        );
    }

    /**
     * @param int|null $delayBetweenRequests
     * @param bool $respectRobots
     * @param bool $rejectNoFollowLinks
     * @param string|null $userAgent
     * @param int|null $maxCrawlCount
     * @param int|null $maxCrawlDepth
     * @param int|null $maxResponseSize
     * @return CrawlerConfiguration
     */
    private function createCrawlConfigurationFromOptions(
        ?int $delayBetweenRequests,
        bool $respectRobots,
        bool $rejectNoFollowLinks,
        ?string $userAgent,
        ?int $maxCrawlCount,
        ?int $maxCrawlDepth,
        ?int $maxResponseSize
    ): CrawlerConfiguration
    {
        $crawlConfiguration = new CrawlerConfiguration();

        if ($delayBetweenRequests !== null) {
            $crawlConfiguration->setDelayBetweenRequests($delayBetweenRequests);
        }

        $crawlConfiguration->setRespectRobots($respectRobots);
        $crawlConfiguration->setRejectNoFollowLinks($rejectNoFollowLinks);

        if (!empty($userAgent)) {
            $crawlConfiguration->setUserAgent($userAgent);
        }

        if (!empty($maxCrawlCount)) {
            $crawlConfiguration->setMaximumCrawlCount($maxCrawlCount);
        }

        if (!empty($maxCrawlDepth)) {
            $crawlConfiguration->setMaximumCrawlDepth($maxCrawlDepth);
        }

        if (!empty($maxResponseSize)) {
            $crawlConfiguration->setMaximumResponseSize($maxResponseSize);
        }

        return $crawlConfiguration;
    }

    /**
     * @param string|null $inputData
     * @return array
     */
    private function createTrimmedArray(?string $inputData): array
    {
        if ($inputData === null) {
            return [];
        }

        $arrayList = explode(',', $inputData);

        array_walk($arrayList, function (&$value) {
            $value = trim($value);
        });

        return $arrayList;
    }
}
