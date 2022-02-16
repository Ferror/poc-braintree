<?php
use Braintree\Gateway;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/vendor/autoload.php';

$app = AppFactory::create();
$gateway = new Gateway(json_decode(file_get_contents('.env.json'), true, 512, JSON_THROW_ON_ERROR)['backend']);

$app->get('/', new \Ferror\HomeAction());

$app->options('/{routes:.+}', function (Request $request, Response $response) {
    return $response
        ->withHeader('Access-Control-Allow-Origin', 'http://localhost:63342')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});

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

    $response = $response
        ->withHeader('Access-Control-Allow-Origin', 'http://localhost:63342')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
    $response->getBody()->write(json_encode($result, JSON_THROW_ON_ERROR));

    return $response;
});

$app->map(['GET'], '/braintree/token', new \Ferror\CreateBraintreeTokenAction($gateway));

$app->map(['POST'], '/braintree/payment-method', static function (Request $request, Response $response) use ($gateway) {
    $body = json_decode($request->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
    $memory = json_decode(file_get_contents('memory/customer.json'), true, 512, JSON_THROW_ON_ERROR);

    $result = $gateway->paymentMethod()->create([
        'customerId' => $memory['customer']['braintree_id'],
        'paymentMethodNonce' => $body['nonce'],
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
            'verifyCard' => true,
            'verificationMerchantAccountId' => 'landingiUSD',
            'verificationAmount' => '1.00',
        ]
    ]);

    $response = $response
        ->withHeader('Access-Control-Allow-Origin', 'http://localhost:63342')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
    $response->getBody()->write(json_encode(['token' => $result->paymentMethod->token], JSON_THROW_ON_ERROR));

    return $response;
});

$app->map(['POST'], '/braintree/payment-method-nonce', static function (Request $request, Response $response) use ($gateway) {
    $body = json_decode($request->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

    $response = $response
        ->withHeader('Access-Control-Allow-Origin', 'http://localhost:63342')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
    $response->getBody()->write(json_encode(['nonce' => $gateway->paymentMethodNonce()->create($body['token'])->paymentMethodNonce->nonce], JSON_THROW_ON_ERROR));

    return $response;
});

/**
 * 1. Cancel all other subscriptions
 * 2. Create pending or active subscription
 */
$app->map(['POST'], '/payments/subscriptions', function (Request $request, Response $response) use ($gateway) {
    $body = json_decode($request->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

    if (isset($body['nonce'])) {
        $result = $gateway->subscription()->create([
            'paymentMethodNonce' => $body['nonce'],
            'merchantAccountId' => 'landingiUSD',
            'planId' => 'agency_19_months_12_usd'
        ]);
    } else {
        $memory = json_decode(file_get_contents('memory/customer.json'), true, 512, JSON_THROW_ON_ERROR);
        $result = $gateway->subscription()->create([
            'paymentMethodToken' => $gateway->customer()->find($memory['customer']['braintree_id'])->paymentMethods[0]->token,
            'merchantAccountId' => 'landingiUSD',
            'planId' => 'agency_19_months_12_usd'
        ]);
    }

    $response = $response
        ->withHeader('Access-Control-Allow-Origin', 'http://localhost:63342')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
    $response->getBody()->write(json_encode([$result], JSON_THROW_ON_ERROR));

    return $response;
});

$app->map(['POST'], '/payments/transactions', function (Request $request, Response $response) use ($gateway) {
    $memory = json_decode(file_get_contents('memory/customer.json'), true, 512, JSON_THROW_ON_ERROR);
    $result = $gateway->transaction()->sale([
        'customerId' => $memory['customer']['braintree_id'],
        'merchantAccountId' => 'landingiUSD',
        'amount' => 100.00,
        'options' => [
            'submitForSettlement' => true,
        ],
    ]);

    $response = $response
        ->withHeader('Access-Control-Allow-Origin', 'http://localhost:63342')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
    $response->getBody()->write(json_encode([$result], JSON_THROW_ON_ERROR));

    return $response;
});

$app->run();
