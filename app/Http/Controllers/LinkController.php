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
        $dbLinks = []; //links retrieved from database
        $crawlerStatus = null; //crawler response object
        $perPage = 30; //records to show per page
        
        $method = $request->method();
        switch($method){
            /* Fires on paginator navigation */
            case 'GET':
                $dbLinks = Link::where('web_domain', '=', $domainName)->paginate($perPage);
                break;

            /* Fires when requested to retrieve links from selected url */
            case 'POST':
                /* Retirieve all records of provided url if it exists in database */
                $dbLinks = Link::where('web_domain', '=', $domainName)->paginate($perPage);
                
                if($dbLinks->count() > 0){
                    /* Flag to show the corresponding message when url found in database */
                    $domainExists = true;
                } else {
                    /* Run the crawler and save to database */
                    $crawlerStatus = $this->runCrawler($domainName, $depth);
                    
                    /* Retrieve the newly created records */
                    $dbLinks = Link::where('web_domain', '=', $domainName)->paginate($perPage);
                }
                break;

            /* Fires when requested to refresh records of an existing url */
            case 'PUT':
                /* Remove previous database records of this domain */
                $deleted = $this->deleteDomain($domainName);

                /* Run the crawler and save to database */
                $crawlerStatus = $this->runCrawler($domainName, $depth);
                
                /* Retrieve the newly created records */
                $dbLinks = Link::where('web_domain', '=', $domainName)->paginate($perPage);
                break;
        }

        /* Prepare paginator values for custom display */
        if(!$request->page || $request->page == 1){
            /* Handle 1st page */
            $firstRecord = 1;
            $lastRecord = $perPage;
        } else {
            /* Handle 2nd page and up */
            $firstRecord = ($request->page - 1) * $perPage + 1;
            $lastRecord = $firstRecord + $perPage - 1;
        }
        /* Handle last page */
        if($lastRecord > $dbLinks->total()){
            $lastRecord = $dbLinks->total();
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
            'firstRecord' => $firstRecord,
            'lastRecord' => $lastRecord,
        ])->with('idx', (request()->input('page', 1) - 1) * $perPage);
    }

    /**
     * Handle the web crawler and store the results
     *
     * @param string $domain
     * @param integer $depth
     * @return object $crawlResponse - links and statuses
     */
    private function runCrawler($domain, $depth){
        $webCrawler = new WebLinkCrawler();
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
