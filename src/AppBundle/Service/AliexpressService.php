<?php


namespace AppBundle\Service;


use AppBundle\Model\clEPNAPIAccess;
use GuzzleHttp\Client;
use JonnyW\PhantomJs\Client as PhantomJS;
use Symfony\Component\DependencyInjection\ContainerInterface as Container;

class AliexpressService
{
    private $container;
    private $html;

    public function __construct($url, $container, $html = false)
    {

        $this->container = $container;
        $this->html = (empty($html)) ? $this->getHtmlByUrl($url) : $html;
    }

    private function getHtmlByUrl($url) {
        $client = PhantomJS::getInstance();
        $client->getEngine()->addOption('--load-images=true');
        $client->getEngine()->setPath($this->container->getParameter('bin_directory') . '/phantomjs.exe');
        $request = $client->getMessageFactory()->createRequest($url, 'GET');
        $response = $client->getMessageFactory()->createResponse();
        $client->send($request, $response);
        return $response->getContent();
    }

    public function getGif()
    {
        preg_match('/(src=")+(\S*mp4)/', $this->html, $res);

        $productUrlVideo = (!empty($res[2])) ? $res[2] : false;

        $gifPatch = '';

        if (!empty($productUrlVideo)) {

            $tmpFile = tempnam($this->container->getParameter('downlaods_directory'), 'guzzle-download');
            $client = new Client(array(
                'base_uri' => '',
                'verify' => false,
                'sink' => $tmpFile,
                'curl.options' => array(
                    'CURLOPT_RETURNTRANSFER' => true,
                    'CURLOPT_FILE' => $tmpFile
                )
            ));

            $client->get($productUrlVideo);

            $videoPath = str_replace('.tmp', '.mp4', $tmpFile);
            rename($tmpFile, $videoPath);



            $ffmpeg = \FFMpeg\FFMpeg::create([
                'ffmpeg.binaries' => $this->container->getParameter('bin_directory') . '/ffmpeg.exe',
                'ffprobe.binaries' => $this->container->getParameter('bin_directory') . '/ffprobe.exe',
            ]);

            $video = $ffmpeg->open($videoPath);
            $gifPatch = str_replace('.mp4', '.gif', $videoPath);
            $video
                ->gif(\FFMpeg\Coordinate\TimeCode::fromSeconds(5), new \FFMpeg\Coordinate\Dimension(480, 360), 25)
                ->save($gifPatch);
        }

        return $gifPatch;
    }


    public function getImages()
    {


        preg_match('/("imagePathList":\[)+("\S*.jpg")],"name/', $this->html, $res);

        $imagesUrl = [];
        // If image isset
        if (!empty($res)) {
            $imagesUrl = explode(',', str_replace('"', '', $res[2]));
        }


        $imagesPaths = [];

        foreach ($imagesUrl as $imageUrl) {
            $tmpFile = tempnam($this->container->getParameter('downlaods_directory'), 'guzzle-download');
            $client = new Client(array(
                'base_uri' => '',
                'verify' => false,
                'sink' => $tmpFile,
                'curl.options' => array(
                    'CURLOPT_RETURNTRANSFER' => true,
                    'CURLOPT_FILE' => $tmpFile
                )
            ));
            $client->get($imageUrl);

            $fileNewName = str_replace('.tmp', '.png', $tmpFile);
            rename($tmpFile, $fileNewName);
            $imagesPaths[] = $fileNewName;
        }

        return $imagesPaths;

    }

    public function getEpnUrl($productUrl)
    {

        preg_match('/item\/(\d{1,})/', $productUrl, $res);

        $productId = $res[1];

        $epn = new clEPNAPIAccess('734f7994f09c657e427e230a99b052d5', 'pwugt1a5hmkji4qwv40rlceosmymdk8m');
        $epn->AddRequestGetOfferInfo('offer_info_rui', $productId, 'ru', 'RUR');
        $epn->RunRequests();

        return $epn->getRequestResults()['offer_info_rui']['offer']['url'];

    }

    public function getEpnUrlShort($url)
    {
        $client = new \GuzzleHttp\Client();

        $res = $client->request('POST', 'http://save.ali.pub/get-url.php', [
            'form_params' => [
                'url' => $url,
                'search-button' => 'OK'
            ]
        ]);

        $epnPage = (string)$res->getBody()->getContents();

        preg_match('/value="(http:\/\/ali.pub\/\w+)/', $epnPage, $res);

        return $res[1];
    }

    public function getPostText($postParams)
    {



        // Get discount
        preg_match('/(<meta property="og:title" content="\d*.\d*.руб. )(\d*)/', $this->html, $res);
        $productDiscount = $res[2];

        // Get Feedback Rating
        preg_match('/"averageStar":"+(\d.\d)/', $this->html, $res);
        $productFeedbackRating = $res[1];

        // Get shipping
        $productShipping = strpos($this->html, 'Бесплатная доставка');


        $message = '🔥 ' . $postParams['productName'] . '
';
        $message .= '✅ ' . $postParams['epnUrl'] . '

';
        $message .= ($productDiscount) ? '‼ Скидка ' . $productDiscount . '%
' : '';
        $message .= ($productFeedbackRating && $productFeedbackRating != '0.0') ? '⭐ Отзывы ' . $productFeedbackRating . '/5
' : '';
        $message .= ($productShipping) ? '🚚 Бесплатная доставка!

' : '';
        $message .= $postParams['productHashtag'] . ' - похожие товары.';


        return $message;
    }

}