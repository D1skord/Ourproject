<?php


namespace AppBundle\Controller;

use AppBundle\Service\AliexpressService;
use AppBundle\Service\VkService;
use Couchbase\Document;
use GuzzleHttp\Client;
use JonnyW\PhantomJs\Client as PhantomJS;
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

        $client = PhantomJS::getInstance();

        // $client->getEngine()->setPath($this->getParameter('bin_directory') . '/phantomjs.exe');
        $client->phantomJS = $this->getParameter('bin_directory') . '/phantomjs.exe';


//        $client->getEngine()->setPath($this->getParameter('bin_directory') . '/phantomjs.exe');

        //$client->setBinDir('absolute_path/bin');
        //$client->setPhantomJs('phantomjs.exe');

        /**
         * @see JonnyW\PhantomJs\Http\Request
         **/
        $request = $client->getMessageFactory()->createRequest('GET', 'https://ru.aliexpress.com/item/33037922702.html');

        /**
         * @see JonnyW\PhantomJs\Http\Response
         **/
        $response = $client->getMessageFactory()->createResponse();


        // Send the request

        $client->send($request, $response);
        if ($response->getStatus() === 200) {

            // Dump the requested page content
            echo $response->getContent();
        }


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


    /**
     * @Route("/aliexperts/add_post", name="aliexperts_add_post")
     */
    public function addPostAction(Request $request, VkService $vkService)
    {

        if ($productUrl = $request->request->get('url')) {

            try {
                $vkService->createPost([
                    'url' => $request->request->get('url'),
                    'postName' => $request->request->get('name'),
                    'productHashtag' => $request->request->get('hashtag'),
                    'html' => $request->request->get('html'),
                ]);
                $this->addFlash('success', 'Пост добавлен!');
            } catch
            (\Exception $e) {
                $this->addFlash('danger', 'Произошла ошибка: ' . '<pre>'.$e.'</pre>');
            }
        }


        // replace this example code with whatever you need
        return $this->render('AppBundle:aliexperts:add_post.html.twig', ['base_dir' => realpath($this->getParameter('kernel.project_dir')) . DIRECTORY_SEPARATOR,]);
    }

}