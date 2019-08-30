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

            $productName = $request->request->get('name');
            $productHashtag = $request->request->get('hashtag');

            $productUrl = $request->request->get('url');
            $productEpnUrlShort = $this->getEpnUrlShort($this->getEpnUrl($productUrl));

            dump($productEpnUrlShort);



            $vk = new vk([
                'client_id' => 7112156, // (обязательно) номер приложения
                'secret_key' => '638fa51b638fa51b638fa51b0763e320c76638f638fa51b3eec6c3787277aaa0a2bfcd9', // (обязательно) получить тут https://vk.com/editapp?id=12345&section=options где 12345 - client_id
                'user_id' => 54213803, // ваш номер пользователя в вк
                'access_token' => '4bab5b54fe0504ef9f13fe2e4ca0f88aa8a1eec560cf9daee6c7221190d1fceb4efe3b4e739dc0956c241',
                'scope' => 'wall,photos', // права доступа
                'v' => '5.62' // не обязательно
            ]);


            $client = new Client();
            $res = $client->request('GET', $productUrl, []);

            $productHtml = $res->getBody()->getContents();

            /* Parse images */
            preg_match('/("imagePathList":\[)+("\S*.jpg")],"name/', $productHtml, $res);

            $imagesUrl = explode(',', str_replace('"', '', $res[2]));

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
                'filter' => 'postponed '
            ]);
            $postDate = $response['items'][count($response['items']) - 1]['date'];
            /* /Get date */


            $message = '🔥 ' . $productName . '
            ';
            $message .= '✅ ' . $productEpnUrlShort . '
            
            ';
            $message .= ($productDiscount) ? '‼ Скидка ' . $productDiscount . '%
            ' : '';
            $message .= ($productFeedbackRating) ? '⭐ Отзывы ' . $productFeedbackRating . '%
            ' : '';
            $message .= ($productShipping) ? '🚚 Бесплатная доставка!
            
            ' : '';
            $message .= $productHashtag.' - похожие товары.';

        $attachments = $vk->upload_photo(185235787, $imagesPaths);
        $response = $vk->wall->post([
            'message' => $message,
            'attachments' => implode(',', $attachments),
            'owner_id' => '-185235787',
            'publish_date' => $postDate + 14400,

        ]);

        }


        // replace this example code with whatever you need
        return $this->render('AppBundle:aliexperts:add_post.html.twig', [
            'base_dir' => realpath($this->getParameter('kernel.project_dir')) . DIRECTORY_SEPARATOR,
        ]);
    }

}