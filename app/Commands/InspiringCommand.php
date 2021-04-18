<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Collection;
use LaravelZero\Framework\Commands\Command;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\DomCrawler\Crawler as DomCrawler;

class InspiringCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'run';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Crawl Product';

    protected $results = [];

    public function handle()
    {
        $startPage = 1;
        $endPage = 1;
        foreach (range($startPage, $endPage) as $page) {
            $url = 'https://www.tokopedia.com/p/handphone-tablet/handphone?ob=5&page=' . $page;

            $html = \Cache::remember($url, now()->addDays(), function () use ($url) {
                return Browsershot::url($url)
                    ->userAgent('PostmanRuntime/7.26.10')
                    ->height(5000)
                    ->setOption('addScriptTag', json_encode(['content' => 'window.scrollTo(0, document.body.scrollHeight);']))
                    ->waitUntilNetworkIdle()
                    ->delay(10000)
                    ->bodyHtml();
            });

            $crawler = new DomCrawler($html);
            $productListSelector = '.css-bk6tzz.e1nlzfl3 a';

            $crawler->filter($productListSelector)->each(function (DomCrawler $node) {
                parse_str($detailUrl = $node->attr('href'), $result);

                $url = $result['r'] ?? $detailUrl;

                $this->results[] = $this->detailCrawler($url);
            });
        }

        $file = fopen('products.csv', 'w');
        fputcsv($file, [
            'title',
            'price',
            'storeName',
            'rating',
            'description',
        ]);

        Collection::make($this->results)
            ->sortByDesc('totalSold')
            ->take(100)
            ->each(function ($result) use ($file) {
                fputcsv($file, [
                    $result['title'],
                    $result['price'],
                    $result['storeName'],
                    $result['rating'],
                    $result['description'],
                ]);
            });

        fclose($file);

        $this->info('Complete');
    }

    private function detailCrawler($url)
    {
        $html = \Cache::remember($url, now()->addDay(), function () use ($url) {
            return Browsershot::url($url)
                ->userAgent('PostmanRuntime/7.26.10')
                ->waitUntilNetworkIdle()
                ->bodyHtml();
        });

        $detailCrawler = new DomCrawler($html);
        $productNameSelector = '[data-testid="lblPDPDetailProductName"]';
        $priceSelector = '[data-testid="lblPDPDetailProductPrice"]';
        $storeName = '[data-testid="llbPDPFooterShopName"]';
        $ratingSelector = '[data-testid="lblPDPDetailProductRatingNumber"]';
        $descriptionSelector = '[data-testid="lblPDPDescriptionProduk"]';
        $totalSoldSelector = '[data-testid="lblPDPDetailProductSoldCounter"]';

        $data = [
            'title' => $this->getText($detailCrawler, $productNameSelector),
            'price' => $this->getText($detailCrawler, $priceSelector),
            'storeName' => $this->getText($detailCrawler, $storeName),
            'rating' => $this->getText($detailCrawler, $ratingSelector),
            'description' => $this->getText($detailCrawler, $descriptionSelector),
            'totalSold' => filter_var($this->getText($detailCrawler, $totalSoldSelector), FILTER_SANITIZE_NUMBER_INT)
        ];

        $this->info('title: ' . $data['title']);
        $this->info('price: ' . $data['price']);
        $this->info('storeName: ' . $data['storeName']);
        $this->info('rating: ' . $data['rating']);
        $this->info('description: ' . $data['description']);
        $this->info('totalSold: ' . $data['totalSold']);
        $this->info('_______________________________________________________');
        return $data;
    }

    private function getText(DomCrawler $detailCrawler, $selector)
    {
        try {
            return $detailCrawler->filter($selector)->text();
        } catch (\Exception $exception) {
            return null;
        }
    }

     /**
     * Define the command's schedule.
     *
     * @param \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    public function schedule(Schedule $schedule)
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
