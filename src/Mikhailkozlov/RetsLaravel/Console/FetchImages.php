<?php namespace Mikhailkozlov\RetsLaravel\Console;

use Symfony\Component\Console\Input\InputOption,
    Symfony\Component\Console\Input\InputArgument,
    Mikhailkozlov\RetsLaravel\RetsProperty,
    Mikhailkozlov\RetsLaravel\RetsImage;


class FetchImages extends RetsCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'rets:image';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch image for property or any other type. Can be used in a queue.';

    /**
     * Run update
     *
     * @author mkozlov
     *
     */
    public function fire($job = null, $data = null)
    {
        // make sure we have ID from the queue
        if (!is_null($job)) {
            if (array_key_exists('techid', $data)) {
                $job->delete();

                return;
            }
        }

        if ($data == null) {
            $data = [
                'techid'   => $this->option('id'),
                'resource' => $this->option('resource'),
                'quality'  => $this->option('quality'),
            ];
        } else {
            $data = array_merge(['resource' => static::RESOURCE, 'quality' => static::QUALITY], $data);
        }

        if (!isset($data['techid']) || empty($data['techid'])) {
            $this->error('TechID is required to import images');

            return;
        }

        if ($data['techid'] == 'self') {
            $listings = RetsProperty::where('listing_office_shortid', \Config::get('system.sales.office_id', '445sp'))
                ->get(['techid']);

            foreach ($listings as $listing) {
                $imported = $this->importImages($listing->techid, $data['resource'], $data['quality']);
            }
        } else {
            $imported = $this->importImages($data['techid'], $data['resource'], $data['quality']);

        }

        if (!is_null($job)) {
            $job->delete();
        }
    }

    protected function importImages($techid, $resource = 'Property', $quality = 'HiRes')
    {
        // connect
        $this->rets = \App::make('rets');

        // we should have things in DB now, and we can look at that data.
        $listing = RetsProperty::where('techid', $techid)->first();

        if (is_null($listing)) {
            $this->error('Not able to locate listing by techid');
            return false;
        }

        // clean all images
        $listing->photos()->delete();

        $this->line('We\'re expecting ' . $listing->piccount . ' images for '.$listing->listnbr);

        $images = $this->rets->getImage($resource, $listing->techid, '*', $quality);
        $this->line('Images collections:');

        if (is_null($images)) {
            $this->error($listing->techid . ' has no images');
            if ($listing->piccount == 0) {
                return;
            }
            $this->line('Going to get images (' . $listing->piccount . ') one by one');

            for ($p = 1; $p <= $listing->piccount; $p++) {
                $image = $this->rets->getImage($resource, $listing->techid, $p, $quality);
                if (is_null($image)) {
                    continue;
                }
                $file = RetsImage::fromApi($image);
                $listing->photos()->save($file);
            }
            $this->info('Images imported!');

            return;
        }

        $this->line('We have ' . $images->count() . ' images');

        foreach ($images as $i => $image) {
            $file = RetsImage::fromApi($image);
            $listing->photos()->save($file);
        }
    }

    public function getOptions()
    {
        return array(
            array('id', null, InputOption::VALUE_REQUIRED, 'Set RETS ID of the resource', null),
            array('resource', null, InputOption::VALUE_OPTIONAL, 'Set resource ID to fetch', static::RESOURCE),
            array('quality', null, InputOption::VALUE_OPTIONAL, 'Set image quality', static::QUALITY),
        );
    }
}