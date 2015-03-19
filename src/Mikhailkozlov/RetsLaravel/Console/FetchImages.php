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

        $imported = $this->importImages($data['techid'], $data['resource'], $data['quality']);

        if (!is_null($job)) {
            $job->delete();
        }
    }

    protected function importImages($techid, $resource = 'Property', $quality = 'HiRes')
    {
        // connect
        $this->rets = \App::make('rets');

        // we should have things in DB now, and we can look at that data.
        $listing = RetsProperty::where('techid', $techid)->first(['techid', 'piccount']);

        if (is_null($listing)) {
            return false;
        }

        // clean all images
        $listing->photos()->delete();

        $this->line('We\'re expecting ' . $listing->piccount . ' images');

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
                $this->line('Save image #' . $p);
                $file = RetsImage::fromApi($image);
                $file->parent_type = 'Property';
                $file->parent_id = $listing->techid;
                // $file->write($image['file']); // - we're going to move this to async process, as it is optional
                $file->save();
                $this->line('Saved');
            }

            $this->info('Images imported!');

            return;
        }

        $this->line('We have ' . $images->count() . ' images');

        foreach ($images as $i => $image) {
            $this->line('Save image #' . $i);
            $file = RetsImage::fromApi($image);
            $file->parent_type = 'Mikhailkozlov\RetsLaravel\RetsProperty';
            $file->parent_id = $listing->techid;
//            if(!empty($image['file'])) {
//                 $file->write($image['file']); // - we're going to move this to async process, as it is optional
//            }
            $file->save();
            $this->line('Saved');
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