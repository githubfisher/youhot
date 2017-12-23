<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Log;

class DispatchDecodeJson implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $file_path;
    protected $timestamp;
    protected $site;
    protected $order;
    protected $database;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($file_path, $timestamp, $site, $database, $order='default')
    {
        $this->file_path = $file_path;
	$this->site = $site;
	$this->timestamp = $timestamp;
	$this->order = $order;
	$this->database = $database;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (file_exists($this->file_path)) {
            Log::info('Dispatching job...');
            $file_arr = file($this->file_path);
            for ($i=0;$i<count($file_arr);$i++) {
	        if (!empty($file_arr[$i])) {
            	    $job = (new DecodeProductsJson($this->file_path, $this->timestamp, $this->site, $file_arr[$i], $i, $this->database))->onConnection('database')->onqueue($this->order);
            	    dispatch($job);
		} else {
		    Log::info($this->file_path.', Line:'.$i.' is empty!');
	  	}
            }
            Log::info('Dispatch job done!');
        } else {
            Log::error($this->file_path.' isn\'t exist!');
        }
    }
}
