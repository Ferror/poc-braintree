<?php
use Braintree\Gateway;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/vendor/autoload.php';

$app = AppFactory::create();
$gateway = new Gateway(json_decode(file_get_contents('.env.json'), true, 512, JSON_THROW_ON_ERROR));

$app->get('/', new \Ferror\HomeAction());

$app->get('/customers', static function (Request $request, Response $response, array $args) use ($gateway) {
    $result = $gateway->customer()->create([
        'email' => 'email@domain.com',
        'paymentMethodNonce' => 'nonce', //@TODO
        'creditCard' => [
            'billingAddress' => [
                'firstName' => 'Jen',
                'lastName' => 'Smith',
                'company' => 'Braintree',
                'streetAddress' => '123 Address',
                'locality' => 'City',
                'region' => 'State',
                'postalCode' => '12345',
                'countryCodeAlpha2' => 'PL',
            ],
            'options' => [
                'verifyCard' => true
            ],
        ]
    ]);

    $response
        ->withHeader('Content-Type', 'application/json')
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->getBody()->write(json_encode($result->jsonSerialize(), JSON_THROW_ON_ERROR));

    return $response;
});

$app->map(['GET', 'OPTIONS'], '/braintree/token', new \Ferror\CreateBraintreeTokenAction($gateway));

$app->map(['POST'], '/braintree/payment-method-nonce', static function (Request $request, Response $response) use ($gateway) {
    $body = json_decode($request->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

    $result = $gateway->customer()->create([
        'email' => 'email@domain.com',
        'paymentMethodNonce' => $body['token'],
        'creditCard' => [
            'billingAddress' => [
                'firstName' => 'Jen',
                'lastName' => 'Smith',
                'company' => 'Braintree',
                'streetAddress' => '123 Address',
                'locality' => 'City',
                'region' => 'State',
                'postalCode' => '12345',
                'countryCodeAlpha2' => 'PL',
            ],
            'options' => [
                'verifyCard' => true
            ],
        ]
    ]);

    $response
        ->withHeader('Content-Type', 'application/json')
        ->getBody()
        ->write(json_encode(['nonce' => $gateway->paymentMethodNonce()->create($result->customer->paymentMethods[0]->token)->paymentMethodNonce->nonce], JSON_THROW_ON_ERROR));

    return $response;
});

$app->map(['POST'], '/payments/subscriptions', function (Request $request, Response $response) use ($gateway) {
    $body = json_decode($request->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

    $result = $gateway->subscription()->create([
        'paymentMethodNonce' => $body['nonce'],
        'merchantAccountId' => 'landingiUSD',
        'planId' => 'agency_19_months_12_usd'
    ]);

    $response
        ->withHeader('Content-Type', 'application/json')
        ->getBody()
        ->write(json_encode([$result], JSON_THROW_ON_ERROR));

    return $response;
});

$app->run();
