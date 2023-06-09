<?php

namespace App\Http\Controllers;

use App\Models\Link;
use Illuminate\Http\Request;
use App\Http\Helpers\WebLinkCrawler;

class LinkController extends Controller
{
    /**
     * Main request handler and process initiator
     *
     * @param Request $request
     * @return mixed view
     */
    public function processLinks(Request $request){
        $fields = $request->validate([
            'search_url' => 'required',
            'search_depth' => ['integer']
        ]);
        $fields['search_url'] = strip_tags($fields['search_url']);
        
        /* Setup default depth or requested depth */
        $depth = 1;
        if(isset($fields['search_depth']) && intval($fields['search_depth']) > 0){
            $depth = intval($fields['search_depth']);
        }
        
        /* Initialize UI values */
        $domainName = $fields['search_url'];
        $domainExists = false; //flag to notify of exisitng url in database
        $dbLinks = [];
        $errors = null;
        $message = null;
        $timing = null; //by default do not show this status in UI, unless value is set by crawler
        $statuses = null; //optional foramtted string to display link statuses and process timing
        $result = null;

        $crawlerStatus = null;
        

        $method = $request->method();
        switch($method){
            /* Fires when requested to retrieve links from selected url */
            case 'POST':
                
                /* Attempt to retirieve all records of this domain name if exists in database */
                $dbLinks = Link::where('web_domain', '=', $domainName)->get();
                //dd($dbLinks);
                /* URL found, set optional refresh */
                if($dbLinks->count() > 0){
                    //$pageData['links'] = $dbLinks;
                    //$message = 'URL found in database, click to refresh';
                    $domainExists = true;
                } else {
                    
                    $crawlerStatus = $this->runCrawler($domainName, $depth);
                    //dd($crawlerStatus);
                    /* Retrieve the newly created records */
                    $dbLinks = Link::where('web_domain', '=', $domainName)->get();
                    
                    //$errors = $result->errors;
                    //$timing = $result->timing;
                    
                    
                }
                break;

            /* Fires when requested to refresh records of an existing url */
            case 'PUT':
                /* Remove previous database records of this domain */
                $deleted = $this->deleteDomain($domainName);
                /* Run the clawer and save the new records for this domain and selected depth */
                $crawlerStatus = $this->runCrawler($domainName, $depth);
                
                /* Retrieve the newly created records */
                $dbLinks = Link::where('web_domain', '=', $domainName)->get();
                
                //$errors = $result->errors;
                //$timing = $result->timing;
                break;
        }

        if($result){
            $statuses = 'Found ' .$result->liveLinks . ' live links and ' .$result->deadLinks . ' dead links.';
            $statuses .= 'Crawler took ' .$result->crawlTiming . ' sec. and Cleanup took ' .$result->cleanupTiming . ' sec.';
        }

        return view('links', [
            'domain' => $domainName,
            'weblinks' => $dbLinks,
            'errors' => ($crawlerStatus) ? $crawlerStatus->errors : null,
            'domainExists' => $domainExists,
            'processTiming' => ($crawlerStatus) ? $crawlerStatus->timing : null,
            'crawlTiming' => ($crawlerStatus) ? $crawlerStatus->crawlTiming : null,
            'cleanupTiming' => ($crawlerStatus) ? $crawlerStatus->cleanupTiming : null,
            'liveLinks' => ($crawlerStatus) ? $crawlerStatus->liveLinks : null,
            'deadLinks' => ($crawlerStatus) ? $crawlerStatus->deadLinks : null,
        ]);
    }

    /**
     * Excecute the crawler class
     *
     * @param string $domain
     * @param integer $depth
     * @return object $crawlResponse - links and statuses
     */
    private function runCrawler($domain, $depth){
        $webCrawler = new WebLinkCrawler();
        
        /* Run the crawler and get response object with data and statuses */
        $crawlResponse = $webCrawler->processWebLinks($domain, $depth);
        
        /* If crawler ran successfully, insert the data */
        if($crawlResponse->links){
            foreach($crawlResponse->links as $link){
                $this->storeLink($domain, $link);
            }
        }

        return $crawlResponse;
    }

    /**
     * Insert one link record at a time
     *
     * @param string $domain
     * @param string $link
     * @return void
     */
    
    /* Insert one link record at a time */
     public function storeLink($domain, $link){
        Link::create([
            'web_domain' => $domain,
            'web_link' => $link
        ]);
    }

    /* This should be the correct method to insert all results in bulk */
    /* didn't work due to lack of syntax knowledge */

    /* public function storeLinks($domain, $links){
        $data = [];
        foreach($links as $link){
            $data[] = [
                'web_domain' => $domain,
                'web_link' => $link,
            ];
        }
                
        $linkModel = new Link;
        $insertResult = $linkModel->insertMany($data);
        return $insertResult;
    } */

    
    /**
     * Delete records by domain name
     *
     * @param string $domainName
     * @return integer $deleteCount - affected records
     */
    public function deleteDomain($domainName){
        $deleteCount = Link::where('web_domain', $domainName)->delete();
        return $deleteCount;
    }

}
