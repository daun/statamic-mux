# Connecting Mux

Register a Mux account and generate API credentials.

## Account

[Register a Mux account](https://dashboard.mux.com/signup) if you don't have one, then
[create an environment](https://dashboard.mux.com/environments). Use a separate Mux environment for each
Statamic app environment, e.g. `development` and `production`.

## Credentials

The addon communicates with the Mux API using an access token. Create one in the
[Access Token settings](https://dashboard.mux.com/settings/access-tokens) of your Mux dashboard, then save
the generated token id and secret to your environment variables.

```env
MUX_TOKEN_ID=b3b7fa9b-efd6-4723-bed2-032b04e61488
MUX_TOKEN_SECRET=YjNiN2ZhOWItZWZkNi00NzIzLWJlZDItMDMyYjA0ZTYxNDg4/l0eZQPmr1S+z+bftAX3Sq
```

## Test Mode

Mux offers a test mode for evaluating their service without incurring charges for storage or streaming.
All videos uploaded in test mode are watermarked and deleted after 24 hours. This is recommended during
initial setup and on development machines. You can enable test mode from an environment variable.

```env
MUX_TEST_MODE=true
```
