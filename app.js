async function request(method, url, data = null) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();

        xhr.open(method, 'http://localhost:8080' + url, true);
        // xhr.setRequestHeader('Access-Control-Allow-Origin', '*')
        // xhr.setRequestHeader('Access-Control-Request-Method', 'GET')
        // xhr.setRequestHeader('Origin', 'localhost:8080)')
        // xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.onload = () => {
            const status = xhr.status;

            if (status === 200) {
                resolve(JSON.parse(xhr.response));
            } else {
                reject(status);
            }
        };
        xhr.send(JSON.stringify(data));
    });
}

document.querySelector('#billing-data').addEventListener('submit', (event) => {
    event.preventDefault();
    console.log('billing-data - form');
});

document.querySelector('#cc').addEventListener('submit', (event) => {
    event.preventDefault();
    console.log('cc - form');

    //MASTERCARD
    run(
        '5555555555554444',
        '11/23',
        '111'
    )

    //VISA
    // run(
    //     '4217651111111119',
    //     '11/23',
    //     '111'
    // )
});

document.querySelector('#transaction').addEventListener('submit', (event) => {
    event.preventDefault();
    console.log('transaction - form');

    request('POST', '/payments/transactions')
        .catch(function (error) {
            console.log(error);
        })
});

async function run(card_number, expiration_date, cvv_number)
{
    console.log({
        card_number,
        expiration_date,
        cvv_number
    })

    request('POST', '/customers')
    .catch(function (error) {
        console.log(error);
    })


    const { token } =  await request('GET', '/braintree/token')

braintree.client
    .create({
        authorization: token
    })
    .then(function (clientInstance) {
        clientInstance
            .request({
                endpoint: 'payment_methods/credit_cards',
                method: 'post',
                data: {
                    creditCard: {
                        number: card_number,
                        expirationDate: expiration_date,
                        cvv: cvv_number,
                        options: {
                            validate: true,
                        }
                    },
               }
            })
            .then(function (response) {
                console.log('creditCardResponse', response);
                const creditCardResponse = response;

                request('POST', '/braintree/payment-method', {
                    token: creditCardResponse.creditCards[0].nonce
                })
                .then(function (response) {
                    console.log('Create Payment Method', response);
                    braintree.threeDSecure
                        .create({
                            version: 2, // Will use 3DS 2 whenever possible
                            client: clientInstance
                        })
                        .then(function (threeDSecureInstance) {
                            // console.log(response.nonce);
                            threeDSecureInstance
                                .verifyCard({
                                    amount: 1788.00,
                                    nonce: response.nonce, // Example: hostedFieldsTokenizationPayload.nonce
                                    bin: creditCardResponse.creditCards[0].bin,
                                    email: "email@domain.com",
                                    onLookupComplete: function (data, next) {
                                        next();
                                    }
                                })
                                .then(function (response) {
                                    //liabilityShifted indicates that 3D Secure worked and authentication succeeded.
                                    //This will also be true if the issuing bank does not support 3D Secure, but the payment method does.
                                    if (response.liabilityShifted === true) {
                                        console.log('User succeeded 3DS verification', response)
                                        request('POST', '/payments/subscriptions', {
                                            nonce: response.nonce
                                        })
                                        .then(function (response) {
                                            console.log(response);
                                        })
                                        .catch(function (error) {
                                            console.log(error);
                                        })

                                        return;
                                    }

                                    //When liabilityShiftPossible is true that means it should check the 3DS.
                                    if (response.liabilityShiftPossible === true) {
                                        if (response.liabilityShifted === true) {
                                            console.log('User succeeded 3DS verification', response)

                                            request('POST', '/payments/subscriptions', {
                                                nonce: response.nonce
                                            })
                                            .then(function (response) {
                                                console.log(response);
                                            })
                                            .catch(function (error) {
                                                console.log(error);
                                            })
                                        } else {
                                            console.log('User failed 3DS verification', response)
                                        }

                                        return;
                                    }

                                    //This card was ineligible for 3D Secure.
                                    if (response.liabilityShiftPossible === false && response.liabilityShifted === false) {
                                        console.log('Create Transaction', response.nonce);

                                        request('POST', '/payments/subscriptions', {
                                            nonce: response.nonce
                                        })
                                        .then(function (response) {
                                            console.log(response);
                                        })
                                        .catch(function (error) {
                                            console.log(error);
                                        })
                                    }
                                })
                                .catch(function (error) {
                                    console.log(error);
                                });
                            })
                   })
            })
    })
}
