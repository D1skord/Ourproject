<?php


namespace AppBundle\Service;


use AppBundle\Model\vk;
use Symfony\Component\DependencyInjection\ContainerInterface as Container;

class VkService
{

    private $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function getPostDate($vk)
    {

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

        return $postDate;
    }

    public function createPost($params)
    {
        $vk = new vk([
            'client_id' => 7112156, // (обязательно) номер приложения
            'secret_key' => '638fa51b638fa51b638fa51b0763e320c76638f638fa51b3eec6c3787277aaa0a2bfcd9', // (обязательно) получить тут https://vk.com/editapp?id=12345&section=options где 12345 - client_id
            'user_id' => 54213803, // ваш номер пользователя в вк
            'access_token' => '8ca01029cd5713d2bb8d5c8c5d13df5ea4182969ee2ce3f81a47ff35a14dcec41638b6a53f27637900907',
            //'access_token' => '8c1efb4662e73b1975d537d522ffb38d6e301db74b00512b1c61ffb3596e8d8c5098d5ecb9f5f0eeb1e95',
            'scope' => 'wall,photos,docs', // права доступа
            'v' => '5.62' // не обязательно
        ]);

        $aliexpress = new AliexpressService($params['url'], $this->container, $params['html']);

        //$attachments = (!empty($gifPatch)) ? $vk->upload_doc_well(185235787, $gifPatch) : implode(',',$vk->upload_photo(185235787, $imagesPaths));
        $attachments = (!empty($gifPatch = $aliexpress->getGif())) ? $vk->upload_doc($gifPatch) : implode(',', $vk->upload_photo(185235787, $aliexpress->getImages()));

        $postParams = [
            'productName' => $params['postName'],
            'productHashtag' => $params['productHashtag'],
            'epnUrl' => $aliexpress->getEpnUrlShort($aliexpress->getEpnUrl($params['url'])),
        ];

        $params = [
            'message' => $aliexpress->getPostText($postParams),
            'owner_id' => '-185235787',
            'publish_date' => $this->getPostDate($vk),
            'attachments' => $attachments
        ];

        $response = $vk->wall->post($params);

    }
}