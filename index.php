<?php

require_once('vendor/autoload.php');

$top_level = 'https://mail.gna.org/listinfo';
$archive   = 'https://mail.gna.org/public';

use GuzzleHttp\Client;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

$client = new Client([
    'base_uri' => 'https://mail.gna.org',
    'timeout'  => 5.0,
    'verify'   => false,
]);

$fs = new Filesystem();

$full_list = $client->request('GET', 'listinfo');


// Resp
/*
 * <tr>
 *  <td><a href="listinfo/zhang-cvs"><strong>Zhang-cvs</strong></a></td>
 *  <td>Commits on the Zhang Editor CVS repositories.</td>
 * </tr>
 */

if($full_list->getStatusCode() == 200) {
    $body = (string) $full_list->getBody();
    
    preg_match_all('/<a href=\"([^\"]+)\"><strong>(.*?)<\/strong>/m', $body, $matches);
}

foreach($matches[1] as $i => $url) {
    $urls[$matches[2][$i]] = $url;
}

foreach($urls as $project_name => $link) {
    list($junk, $proper) = explode('/', $link);
    
    try {
        $fs->mkdir('mbox/' . $project_name);
    } catch (IOExceptionInterface $ex) {
        echo "An error occurred while creating your directory at ".$e->getPath();
    }
    
    try {
        $project_page = $client->request('GET', 'public/' . $proper);
    }  catch (GuzzleHttp\Exception\ClientException $ex) {
        echo('Download of project ' . $project_name . ' failed: ' . $ex->getCode() . ' -- ' . $ex->getRequest()->getUri() . "\n");
        continue;
    }
    $body = (string) $project_page->getBody();
    // MBox
    // <a href="2007-08.mbox.gz">
    // https://mail.gna.org/public/a2jmidid-commits/2010-10.mbox.gz
    // 
    // By Date
    // <a href="2017-05/index.html">Index by Date</a>
    // https://mail.gna.org/public/bloryn-dev/2017-05/index.html
    // 
    // By Thread
    // <a href="2017-05/threads.html">
    // https://mail.gna.org/public/bloryn-dev/2017-05/threads.html
    
    preg_match_all('/<a href=\"([^\"]+\.gz)\">/m', $body, $gzs);
    preg_match_all('/<a href=\"([^\"]+index\.html)\">/m', $body, $dates);
    preg_match_all('/<a href=\"([^\"]+threads\.html)\">/m', $body, $threads);
    
    /*
    foreach($gzs[1] as $gz) {
        try {
            $client->request(
                    'GET',
                    'public/' . $proper . '/' . $gz,
                    [
                        'sink' => 'mbox/' . $project_name . '/' . $gz
                    ]);
        } catch (GuzzleHttp\Exception\ClientException $ex) {
            echo('Download Failed: ' . $ex->getCode() . ' -- ' . $ex->getRequest()->getUri() . "\n");
        }
    }
     *
     */
    
    echo('Processing ' . $proper . ' by date' . "\n");
    
    // Processes Archive page for threads by date
    foreach($dates[1] as $date) {
        // $date = 2012-07/index.html
        list($dir, $junk) = explode('/', $date);
        echo('  -' . $dir . "\n");
    
        try {
            $fs->mkdir('html/bydate/' . $project_name . '/' . $dir);
        } catch (IOExceptionInterface $ex) {
            echo "An error occurred while creating your directory at ".$e->getPath();
            continue;
        }        
        
        // Downloads Date List Index Page
        try {
            $date_index = $client->request(
                    'GET', 
                    'public/' . $proper . '/' . $date,
                    [
                        'sink' => 'html/bydate/' . $project_name . '/' . $date
                    ]);
        } catch (GuzzleHttp\Exception\ClientException $ex) {
            echo('Download of Date list failed: ' . $ex->getCode() . ' -- ' . $ex->getRequest()->getUri() . "\n");
        }
        
        $body = $date_index->getBody();
        
        preg_match_all('/<a name=\"[^\"]+\" href=\"(msg[^\"]+\.html)\">/m', $body, $date_threads);
        
        // Processes Each Date Page, and downloads thread htmls
        foreach($date_threads[1] as $date_thread) {
            try {
                $client->request(
                    'GET', 
                    'public/' . $proper . '/' . $dir . '/' . $date_thread,
                    [
                        'sink' => 'html/bydate/' . $project_name . '/' . $dir . '/' . $date_thread
                    ]);
            } catch (GuzzleHttp\Exception\ClientException $ex) {
                echo('Download of Date thread failed: ' . $ex->getCode() . ' -- ' . $ex->getRequest()->getUri() . "\n");
            }
        }
    }

    echo('Processing ' . $proper . ' by thread' . "\n");    
    //Processes Threads
    foreach($threads[1] as $thread) {
        // $date = 2012-07/index.html
        list($dir, $junk) = explode('/', $thread);
        echo('  -' . $dir . "\n");
    
        try {
            $fs->mkdir('html/bythread/' . $project_name . '/' . $dir);
        } catch (IOExceptionInterface $ex) {
            echo "An error occurred while creating your directory at ".$e->getPath();
            continue;
        }        
        
        // Downloads Date List Index Page
        try {
            $date_index = $client->request(
                    'GET', 
                    'public/' . $proper . '/' . $thread,
                    [
                        'sink' => 'html/bythread/' . $project_name . '/' . $thread
                    ]);
        } catch (GuzzleHttp\Exception\ClientException $ex) {
            echo('Download of Date list failed: ' . $ex->getCode() . ' -- ' . $ex->getRequest()->getUri() . "\n");
        }
        
        $body = $date_index->getBody();
        
        preg_match_all('/<a name=\"[^\"]+\" href=\"(msg[^\"]+\.html)\">/m', $body, $date_threads);
        
        // Processes Each Thread Page, and downloads thread htmls
        foreach($date_threads[1] as $date_thread) {
            try {
                $client->request(
                    'GET', 
                    'public/' . $proper . '/' . $dir . '/' . $date_thread,
                    [
                        'sink' => 'html/bythread/' . $project_name . '/' . $dir . '/' . $date_thread
                    ]);
            } catch (GuzzleHttp\Exception\ClientException $ex) {
                echo('Download of Date thread failed: ' . $ex->getCode() . ' -- ' . $ex->getRequest()->getUri() . "\n");
            }
        }
    }
}