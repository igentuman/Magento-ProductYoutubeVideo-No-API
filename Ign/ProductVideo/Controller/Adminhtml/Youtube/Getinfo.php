<?php
/*
 * author: Siarhei Astapchyk
 */

namespace Ign\ProductVideo\Controller\Adminhtml\Youtube;

use Exception;
use Laminas\Http\Client;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;

class Getinfo extends Action
{
    private JsonFactory $resultJsonFactory;

    public function __construct(Context $context, JsonFactory $resultJsonFactory)
    {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
    }

    protected function requestInfo($id)
    {
        $client = new Client('https://www.youtube-nocookie.com/embed/' . $id);
        $client->setOptions([
            'adapter'     => Client\Adapter\Curl::class,
            'sslverifypeer' => false,
            'sslverifypeername' => false,
            'curloptions' => [
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_SSL_VERIFYPEER => false
            ]
        ]);
        try {
            $response = $client->send()->getBody();

            //pretty unstable logic
            preg_match('@thumbnailPreviewRenderer\\\"\:(.+)\}\}\}\}\}\,@m', $response, $matches);

            $fixedJson = str_replace('\"', '"', $matches[1]) . '}}}}';

            $data = \Safe\json_decode($fixedJson, true);

            //probably we can get more information
            $title = $data['title']['runs'][0]['text'] ?? '';
            $thumbnails = [
                'default' => $data['defaultThumbnail']['thumbnails'][0] ?? 'https://i.ytimg.com/vi/' . $id . '/default.jpg',
                'medium' => $data['defaultThumbnail']['thumbnails'][2] ?? 'https://i.ytimg.com/vi/' . $id . '/mqdefault.jpg',
                'high' => $data['defaultThumbnail']['thumbnails'][4] ?? 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg',
                'standard' => $data['defaultThumbnail']['thumbnails'][6] ?? 'https://i.ytimg.com/vi/' . $id . '/sddefault.jpg',
                'maxres' => $data['defaultThumbnail']['thumbnails'][8] ?? 'https://i.ytimg.com/vi/' . $id . '/maxresdefault.jpg',
            ];
        } catch (Exception $e) {
            $title = '';
            $thumbnails = [
                'default' => [
                    'url' => 'https://i.ytimg.com/vi/' . $id . '/default.jpg',
                    'width' => 120,
                    'height' => 90
                ],
                'medium' => [
                    'url' => 'https://i.ytimg.com/vi/' . $id . '/mqdefault.jpg',
                    'width' => 320,
                    'height' => 180
                ],
                'high' => [
                    'url' => 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg',
                    'width' => 480,
                    'height' => 360
                ],
                'standard' => [
                    'url' => 'https://i.ytimg.com/vi/' . $id . '/sddefault.jpg',
                    'width' => 640,
                    'height' => 480
                ],
                'maxres' => [
                    'url' => 'https://i.ytimg.com/vi/' . $id . '/maxresdefault.jpg',
                    'width' => 1280,
                    'height' => 720
                ]];
        }
        return [$title, $thumbnails];
    }

    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();
        $response = [];
        $id = $this->getRequest()->getParam('id');
        $info = $this->requestInfo($id);
        $response['items'] = [[
            'id' => $id,
            'author_name' => '',
            'channelId' => '',
            'uploaded' => date(\DateTimeInterface::ATOM, time()),
            'contentDetails' => [
                'duration' => 'PT0M0S',
            ],
            'snippet' => [
                'localized' => [
                    'title' => $info[0]
                ],
                'publishedAt' => date(\DateTimeInterface::ATOM, time()),
                'channelId' => '',
                'title' => $info[0],
                'description' => $info[0],
                'thumbnails' => $info[1]
            ]
        ]];
        return $resultJson->setData($response);
    }
}
