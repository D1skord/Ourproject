<?php


namespace AppBundle\Controller;

use Couchbase\Document;
use GuzzleHttp\Client;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Request;
use AppBundle\Model\vk;
use Symfony\Component\Routing\Annotation\Route;
use AppBundle\Model\clEPNAPIAccess;
use GuzzleHttp\Psr7\Stream;
use Antalaron\VideoGif\VideoGif;


class AliexpertsController extends Controller
{
    /**
     * @Route("/aliexperts", name="aliexperts")
     */
    public function indexAction(Request $request)
    {
        // replace this example code with whatever you need
        return $this->render('AppBundle:aliexperts:index.html.twig', [
            'base_dir' => realpath($this->getParameter('kernel.project_dir')) . DIRECTORY_SEPARATOR,
        ]);
    }

    /**
     * @Route("/aliexperts/epn", name="aliexperts_epn")
     */
    public function epnAction(Request $request)
    {



        // replace this example code with whatever you need
        return $this->render('AppBundle:aliexperts:index.html.twig', [
            'base_dir' => realpath($this->getParameter('kernel.project_dir')) . DIRECTORY_SEPARATOR,
        ]);

    }


    /**
     * @Route("/aliexperts/epn/get_url", name="aliexperts_epn_get_url")
     */
    public function getEpnLinkAction(Request $request)
    {
        $productUrl = $request->request->get('url');
        $urlShort = $this->getEpnUrlShort($productUrl);

        return $this->render('AppBundle:aliexperts:get_epn_url.html.twig', [
            'base_dir' => realpath($this->getParameter('kernel.project_dir')) . DIRECTORY_SEPARATOR,
            'url_short' => $urlShort
        ]);
    }

    private function getEpnUrl($productUrl)
    {

        preg_match('/item\/(\d{1,})/', $productUrl, $res);

        $productId = $res[1];

        $epn = new clEPNAPIAccess('734f7994f09c657e427e230a99b052d5', 'pwugt1a5hmkji4qwv40rlceosmymdk8m');
        $epn->AddRequestGetOfferInfo('offer_info_rui', $productId, 'ru', 'RUR');
        $epn->RunRequests();

        return $epn->getRequestResults()['offer_info_rui']['offer']['url'];

    }

    private function getEpnUrlShort($url)
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

    /**
     * @Route("/aliexperts/add_post", name="aliexperts_add_post")
     */
    public function addPostAction(Request $request)
    {
        $productUrl = $request->request->get('url');
        if (!empty($productUrl)) {


            /* Get video */
            $productUrlMob = str_replace('//ru', '//m.ru', $request->request->get('url'));


            $client = new Client();
            $res = $client->request('GET', $productUrlMob, [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36',
                ]
            ]);

            $productHtmlMob = $res->getBody()->getContents();


            preg_match('/(<source src=")+(\S*mp4)/', $productHtmlMob, $res);


            $productUrlVideo = (!empty($res[2])) ? $res[2] : false;
            $gifPatch = '';
            if (!empty($productUrlVideo)) {


                $tmpFile = tempnam($this->getParameter('downlaods_directory'), 'guzzle-download');
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
                    'ffmpeg.binaries' => $this->getParameter('bin_directory') . '/ffmpeg.exe',
                    'ffprobe.binaries' => $this->getParameter('bin_directory') . '/ffprobe.exe',
                ]);

                $video = $ffmpeg->open($videoPath);
                $gifPatch = str_replace('.mp4', '.gif', $videoPath);
                $video
                    ->gif(\FFMpeg\Coordinate\TimeCode::fromSeconds(1), new \FFMpeg\Coordinate\Dimension(480, 360), 15)
                    ->save($gifPatch);
            }
            /* /Get video */

            $productName = $request->request->get('name');
            $productHashtag = $request->request->get('hashtag');

            $productUrl = $request->request->get('url');
            $productEpnUrlShort = $this->getEpnUrlShort($this->getEpnUrl($productUrl));


            $vk = new vk([
                'client_id' => 7112156, // (обязательно) номер приложения
                'secret_key' => '638fa51b638fa51b638fa51b0763e320c76638f638fa51b3eec6c3787277aaa0a2bfcd9', // (обязательно) получить тут https://vk.com/editapp?id=12345&section=options где 12345 - client_id
                'user_id' => 54213803, // ваш номер пользователя в вк
                'access_token' => '8ca01029cd5713d2bb8d5c8c5d13df5ea4182969ee2ce3f81a47ff35a14dcec41638b6a53f27637900907',
                //'access_token' => '8c1efb4662e73b1975d537d522ffb38d6e301db74b00512b1c61ffb3596e8d8c5098d5ecb9f5f0eeb1e95',
                'scope' => 'wall,photos,docs', // права доступа
                'v' => '5.62' // не обязательно
            ]);



            $client = new Client();
            $res = $client->request('GET', $productUrl, []);

            $productHtml = $res->getBody()->getContents();


            /* Parse images */
            preg_match('/("imagePathList":\[)+("\S*.jpg")],"name/', $productHtml, $res);


            $imagesUrl = [];
            // If image isset
            if (!empty($res)) {
                $imagesUrl = explode(',', str_replace('"', '', $res[2]));
            }


            $imagesPaths = [];

            foreach ($imagesUrl as $imageUrl) {
                $tmpFile = tempnam($this->getParameter('downlaods_directory'), 'guzzle-download');
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
            /* Parse images */



            // Get discount
            preg_match('/(<meta property="og:title" content="\d*.\d*.руб. )(\d*)/', $productHtml, $res);
            $productDiscount = $res[2];

            // Get Feedback Rating
            preg_match('/"averageStar":"+(\d.\d)/', $productHtml, $res);
            $productFeedbackRating = $res[1];

            // Get shipping
            $productShipping = strpos($productHtml, 'Бесплатная доставка');


            /* Get date */
            $response = $vk->wall->get([
                'owner_id' => '-185235787',
                'filter' => 'postponed ',
                'count' => '50'
            ]);

            $addHours = 18000;// 5 hours

            // If there are scheduled posts
            if ($response['count']) {
                $postDate = $response['items'][count($response['items']) - 1]['date'] + $addHours;
            } else {
                $postDate = strtotime("now") + $addHours;
            }
            /* /Get date */


            $message = '🔥 ' . $productName . '
';
            $message .= '✅ ' . $productEpnUrlShort . '

';
            $message .= ($productDiscount) ? '‼ Скидка ' . $productDiscount . '%
' : '';
            $message .= ($productFeedbackRating && $productFeedbackRating != '0.0') ? '⭐ Отзывы ' . $productFeedbackRating . '/5
' : '';
            $message .= ($productShipping) ? '🚚 Бесплатная доставка!

' : '';
            $message .= $productHashtag . ' - похожие товары.';


            $attachments = (!empty($gifPatch)) ? $vk->upload_doc_well(185235787, $gifPatch) : implode(',',$vk->upload_photo(185235787, $imagesPaths));

    
            try {
                $params = [
                    'message' => $message,
                    'owner_id' => '-185235787',
                    'publish_date' => $postDate,
                    'attachments' =>  $attachments
                ];

//                if (!empty($gifPatch)) {
//                    //$params['file'] = $vk->upload_doc_well(185235787, $gifPatch);
//                } else {
//                    $attachments = $vk->upload_photo(185235787, $imagesPaths);
//                    $params['attachments'] = implode(',', $attachments);
//                }

                $response = $vk->wall->post($params);

                $this->addFlash('success', 'Пост добавлен!');
            } catch (\Exception $e) {
                $this->addFlash('danger', 'Произошла ошибка: ' . $e);
            }


        }


        // replace this example code with whatever you need
        return $this->render('AppBundle:aliexperts:add_post.html.twig', [
            'base_dir' => realpath($this->getParameter('kernel.project_dir')) . DIRECTORY_SEPARATOR,
        ]);
    }

}