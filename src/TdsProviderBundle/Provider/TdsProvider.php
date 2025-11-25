<?php

namespace TdsProviderBundle\Provider;

use TdsProviderBundle\Utils\SlackUtils;
use TdsProviderBundle\Utils\KClickClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use TdsProviderBundle\Utils\UrlUtils;

class TdsProvider
{
    private const TDS_URL_PATTERN = 'https://%s/api.php?';

    private const TDS_FALLBACK_URL = 'www.google.com';

    public const TDS_REQUEST_TYPE_DEFAULT = 'default';
    public const TDS_REQUEST_TYPE_TERMS = 'terms';

    /** @var KClickClient */
    private $client;

    /** @var string */
    private $tdsApiUrl;

    /** @var string */
    private $tdsApiKey;

    /** @var string */
    private $tdsHost;

    public function __construct(
        RequestStack $requestStack,
        string $apiUrl,
        string $apiKey,
        ?string $domain
    ) {
        $this->tdsApiUrl = sprintf(self::TDS_URL_PATTERN, $apiUrl);
        $this->tdsApiKey = $apiKey;
        $this->tdsHost = $domain ?? $requestStack->getCurrentRequest()->getHost();
    }

    public function initClient(KClickClient $client = null): void
    {
        if ($this->client) {
            return;
        }

        if ($client) {
            $this->client = $client;

            return;
        }

        $this->client = new KClickClient(
            $this->tdsApiUrl,
            $this->tdsApiKey
        );
    }

    public function doTdsRequest(string $keyword, string $type, Request $request): void
    {
        $this->initClient();

        $this->client->sendAllParams();
        $this->client->currentPageAsReferrer();

        $cookieLandingPage = $request->cookies->get('landing_page', '');
        $this->client->param('site', $this->tdsHost);
        $this->client->param('sub_id_1', $type);
        $this->client->param('sub_id_2', $request->cookies->get('_ga', ''));
        $this->client->param('sub_id_3', $request->cookies->get('ref', ''));
        $this->client->param('sub_id_4', $cookieLandingPage);
        $this->client->param(
            'sub_id_5',
            $cookieLandingPage ? UrlUtils::getUrlPathFromUrl($cookieLandingPage) : $cookieLandingPage
        );
        $this->client->param('sub_id_6', $request->cookies->get('reflink_click_timestamp', ''));
        $customer = json_decode($request->cookies->get('customer', '{}'), true);
        $this->client->param('sub_id_7', $customer['uid'] ?? '');
        $this->client->param('sub_id_8', $request->query->get('referer') ?? $request->headers->get('referer', ''));

        $this->client->keyword($keyword);

        $this->client->forceRedirectOffer();

        $content = $this->client->execute(false, false);
        $headers = $this->client->sendHeaders();

        if (!empty($content) || $this->client->checkHeaders($headers)) {
            $this->checkFallbackTdsRedirect($headers, $this->tdsHost);
            exit;
        }
        SlackUtils::sendMessage(
            sprintf(
                "\nERROR: TDS did not return any result!\n\nDateTime: %s\nDomain: %s, Keyword: %s, Type (sub_id_1): %s",
                date('Y-m-d H:i:s'),
                $this->tdsHost,
                $keyword,
                $type
            )
        );
    }

    private function checkFallbackTdsRedirect($headers, $host): void
    {
        if (empty($headers)) {
            return;
        }
        foreach ($headers as $header) {
            if (strpos($header, 'Location:') === 0 && substr_count($header, self::TDS_FALLBACK_URL)) {
                SlackUtils::sendMessage(
                    sprintf(
                        "\nERROR: TDS return fallback url: %s\n\nDateTime: %s\nDomain: %s",
                        self::TDS_FALLBACK_URL,
                        date('Y-m-d H:i:s'),
                        $host
                    )
                );
                break;
            }
        }
    }
}
