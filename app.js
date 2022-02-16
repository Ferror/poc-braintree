async function request(method, url, data = null) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();

        xhr.open(method, 'http://localhost:8080' + url, true);
        xhr.setRequestHeader('Content-Type', 'application/json');

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

document.querySelector('#pp').addEventListener('submit', (event) => {
    event.preventDefault();
    console.log('paypal - form');

    run_paypal();
});

document.querySelector('#cc').addEventListener('submit', (event) => {
    event.preventDefault();
    console.log('cc - form');

    //MASTERCARD
    run_credit_card(
        '5555555555554444',
        '11/23',
        '111'
    )

    //VISA
    // run_credit_card(
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

async function run_paypal()
{
    request('POST', '/customers')
        .catch(function (error) {
            console.log(error);
        })

    const { token } =  await request('GET', '/braintree/token');

    braintree.client
        .create({
            authorization: token
        })
        .then(function (clientInstance) {
            return braintree.paypalCheckout.create({
                client: clientInstance
            });
        }).then(function (paypalCheckoutInstance) {
            return paypalCheckoutInstance.loadPayPalSDK({
                vault: true,
            });
        })
        .then(function (paypalCheckoutInstance) {
            return paypal.Buttons({
                fundingSource: paypal.FUNDING.PAYPAL,
                createBillingAgreement: function () {
                    return paypalCheckoutInstance.createPayment({
                        flow: 'vault', // Required
                    })
                },
                onApprove: function (data, actions) {
                    console.log('PayPal payment approve', data);
                    return paypalCheckoutInstance.tokenizePayment(data).then(function (payload) {
                        // Submit `payload.nonce` to your server
                        request('POST', '/braintree/payment-method', {
                            nonce: payload.nonce
                        })
                            .then(function (response) {
                                request('POST', '/payments/subscriptions', {
                                    nonce: response.nonce
                                })
                                    .then(function (response) {
                                        console.log(response);
                                    })
                                    .catch(function (error) {
                                        console.log(error);
                                    })
                            })
                    });
                },
                onCancel: function (data) {
                    console.log('PayPal payment canceled', data);
                },
                onError: function (err) {
                    console.error('PayPal payment error', err);
                }
            })
            .render('#paypal-button');
        })
        .then(function () {
            document.querySelector('#pp_submit').style.display = 'none';
        })
}

async function run_credit_card(card_number, expiration_date, cvv_number)
{
    //1. Create customer from Invoice Data
    //2. Fetch Braintree Token from backend
    //3. Authorize Braintree Client SDK
    //4. Tokenize Credit Card
    //5. Create Payment Method
    //6. Create Payment Method Nonce
    //7. Enrich Payment Method Nonce via 3DS
    //8. Create Transaction or Subscription
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
                        nonce: creditCardResponse.creditCards[0].nonce
                    })
                    .then(function (response) {
                        request('POST', '/braintree/payment-method-nonce', {
                            token: response.token
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
                                            amount: "0.00",
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
        })
}
