<?php

namespace App\Http\Helpers;

use DOMDocument;

set_time_limit(60 * 10); //change this according to max excecution time setting

class WebLinkCrawler{
    /* Initial settings */
    const ENV_MAX_RESULTS = 500; //max number of results to process per crawl session, regardless of time limit
    const ENV_MAX_RESPONSE_TIME = 3; //max waiting seconds for http response
    private $statusWhitelist = [200, 301, 302]; //valid http reponse statuses (add more as required)
    
    /* Crawler settings */
    private $rootUrl; //user requested url start point
    private $reqDepth; //user requested depth for recursive drill down
    private $httpScheme = ''; //resolved server scheme (http / https)
    private $domainName = ''; //resolved domain name
    //private $currentDepth = 0; //track current depth within recursion
    private $errors = null; //error messages when applicable
    private $linkResults = []; //array of extracted links for db storage

    /* Report statuses */
    private $liveLinks;
    private $deadLinks;
    private $startTime;
    private $midTime;
    private $endTime;

    /**
     * Main process handler
     * @param string $url
     * @param int $depth
     * @return object
     */
    public function processWebLinks($url, $depth){
        $response = new \stdClass;
        $this->startTime = microtime(true);

        if($this->validateRootUrl($url)){
            $this->crawler($this->rootUrl, $depth);
            $this->midTime = microtime(true);
            $this->removeDeadLinks(); //can be disabled to shorten process time
        };
        
        $this->endTime = microtime(true);

        $response->errors = $this->errors; //error description
        $response->links = $this->linkResults; //array of links
        $response->recordCount = count($this->linkResults); //total links
        $response->timing = round(($this->endTime - $this->startTime), 2); //seconds

        /* Optional calculations if remove dead links if enabled */
        $response->crawlTiming = round(($this->midTime - $this->startTime), 2); //seconds the crawler took
        $response->cleanupTiming = round(($this->endTime - $this->midTime), 2); //seconds the cleanup took
        $response->liveLinks = $this->liveLinks;
        $response->deadLinks = $this->deadLinks;

        return $response;
    }

    /**
     * Check that the requested url is formed correctly
     * @param string $reqUrl
     * @return boolean
     */
    private function validateRootUrl($reqUrl){
        $url = filter_var($reqUrl, FILTER_SANITIZE_URL);
        
        if(!filter_var($url, FILTER_VALIDATE_URL)){
            $this->errors = 'Failed to resolve url';
            return false;
        }

        if(!$parsed = parse_url($url)){
            $this->errors = 'Failed to parse url';
            return false;
        }
        
        if(!(strpos($parsed['host'], 'www') !== false)){
            $this->errors = 'Missing www prefix';
            return false;
        }

        if(!$this->isLiveUrl($url)){
            $this->errors = 'http response error';
            return false;
        }
        
        $this->errors = null;
        $this->httpScheme = $parsed['scheme'];
        $this->domainName = $parsed['host'];
        $this->rootUrl = $url;

        return true;
    }

    /**
     * Check that the http response from reqested link is valid
     * @param string $reqLink
     * @return boolean
     */
    private function isLiveUrl($reqLink){
        $curl = curl_init($reqLink);
        /* Abort if response time is longer than specified */
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, self::ENV_MAX_RESPONSE_TIME); 
        /* Exclude body from result */
        curl_setopt($curl, CURLOPT_NOBODY, true);
        
        $result = curl_exec($curl);
        /* Request fails on no response */
        if(!$result){
            return false;
        }
        
        /* Get the accepted http respone code (as stated in statusWhitelist) */    
        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE); 
        if(!in_array($statusCode, $this->statusWhitelist)){
            return false;
        }
        return true; //on success
    }

    /**
     * Check that the requested link doesn't exist in results to prevent duplicates
     * @param string $link
     * @return boolean
     */
    private function linkExists($link){
        foreach($this->linkResults as $linkResult){
            if($linkResult === $link || $linkResult === $link .'/'){
                return true; //link is duplicate
            }
        }
        return false;
    }

    /* Iterate all link results to remove dead links and report statuses */
    private function removeDeadLinks(){
        $cleanArray = []; //links with successful response
        $live = 0; //report found live links
        $dead = 0; //report found dead links
        for($i=0; $i < count($this->linkResults); $i++){
            if($this->isLiveUrl($this->linkResults[$i])){
                $cleanArray[] = $this->linkResults[$i];
                $live++;
            } else {
                $dead++;
            }
        }
        $this->liveLinks = $live;
        $this->deadLinks = $dead;
        $this->linkResults = $cleanArray;
    }

    /**
     * Pass this link through several cleanup conditions to decide if it should be discarded or kept
     * @param string $link
     * @return boolean
     */
    private function validateLinkToStore($link){
        #1
        if(strpos($link, '..') !== false ||
            strpos($link, '#') !== false ||
            strpos($link, '?') !== false ){
                return false;
            }
        #2
        $regex = "((https?|ftp)\:\/\/)?";
        $regex .= "([a-z0-9+!*(),;?&=\$_.-]+(\:[a-z0-9+!*(),;?&=\$_.-]+)?@)?";
        $regex .= "([a-z0-9-.]*)\.([a-z]{2,3})";
        $regex .= "(\:[0-9]{2,5})?";
        $regex .= "(\/([a-z0-9+\$_-]\.?)+)*\/?";
        $regex .= "(\?[a-z+&\$_.-][a-z0-9;:@&%=+\/\$_.-]*)?";
        $regex .= "(#[a-z_.-][a-z0-9+\$_.-]*)?";
    
        if (!preg_match("/^$regex$/i", $link)) {
            return false;
        }

        #3
        if($parsed = parse_url($link)){
            if(isset($parsed['host']) && $parsed['host'] !== $this->domainName){
                return false;
            }
        }
        return true; //on success
    }

    /**
     * Handle link extraction recursivley via provided depth and add each to results if validated
     * @param string $url
     * @param int $depth
     * @return void
     */
    private function crawler($url, $depth){
        
        /* Exit if max results reached */
        if(count($this->linkResults) >= self::ENV_MAX_RESULTS){
            return;
        }
        
        /* Exit if this link was parsed already */
        static $visited = [];
        if (isset($visited[$url]) || $depth === 0) {
            return;
        }
        $visited[$url] = true;
        
        /* Initialize dom object with scraped html from url */
        $dom = new DOMDocument('1.0');
        @$dom->loadHTMLFile($url);

        /* Get all <a> tag elements and iterate each href attribute to validate the url */
        $elements = $dom->getElementsByTagName('a');
        
        foreach ($elements as $element) {
            $href = $element->getAttribute('href');
            /* Build parsable url from href attribute */
            if(0 !== strpos($href, 'http')){
                $path = '/' . ltrim($href, '/');
                if(!$parts = parse_url($url)){
                    return;
                }
                $href = $parts['scheme'] . '://' . $parts['host'];
                if(isset($parts['path'])){
                    $href .= dirname($parts['path'], 1) . $path;
                }
            }
            
            /* Cleanup, validate and check for duplicates before storing this link */
            if($this->validateLinkToStore($href) && !$this->linkExists($href)){
                $this->linkResults[] = $href;
            }
            
            /* Drill down */
            $this->crawler($href, $depth - 1);
        }
    }
}