<?php
use Braintree\Gateway;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/vendor/autoload.php';

$app = AppFactory::create();
$gateway = new Gateway(json_decode(file_get_contents('.env.json'), true, 512, JSON_THROW_ON_ERROR));

$app->get('/', new \Ferror\HomeAction());

$app->map(['POST'], '/customers', static function (Request $request, Response $response, array $args) use ($gateway) {
    // create Customer in Wfirma
    // create Customer in Braintree

    // create Customer in DB and external ids (braintree, wfrima)
    $result = $gateway->customer()->create([
        'email' => 'email@domain.com',
        'firstName' => 'Jen',
        'lastName' => 'Smith',
        'company' => 'Braintree',
    ]);

    file_put_contents('memory/customer.json', json_encode(['customer' => ['braintree_id' => $result->customer->id]], JSON_THROW_ON_ERROR));

    $response
        ->withHeader('Content-Type', 'application/json')
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->getBody()->write(json_encode($result, JSON_THROW_ON_ERROR));

    return $response;
});

$app->map(['GET', 'OPTIONS'], '/braintree/token', new \Ferror\CreateBraintreeTokenAction($gateway));

$app->map(['POST'], '/braintree/payment-method', static function (Request $request, Response $response) use ($gateway) {
    $body = json_decode($request->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
    $memory = json_decode(file_get_contents('memory/customer.json'), true, 512, JSON_THROW_ON_ERROR);

    //v2
    $result = $gateway->paymentMethod()->create([
        'customerId' => $memory['customer']['braintree_id'],
        'paymentMethodNonce' => $body['token'],
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
            'makeDefault' => true,
        ]
    ]);

    $response
        ->withHeader('Content-Type', 'application/json')
        ->getBody()
        ->write(json_encode(['nonce' => $gateway->paymentMethodNonce()->create($result->paymentMethod->token)->paymentMethodNonce->nonce], JSON_THROW_ON_ERROR));

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
