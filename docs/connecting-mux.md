# Connecting Mux

Register a Mux account and generate API credentials.

## Account

If you haven't already, now is a good time to [register a Mux account](https://dashboard.mux.com/signup).
Once you have an account, you need to [create an environment](https://dashboard.mux.com/environments).
It's recommended to have separate Mux environments for each Statamic app environment like `development` and `production`.

## Credentials

To allow this addon to communicate with the Mux API, you'll need to create an Access Token in the
[Access Token settings](https://dashboard.mux.com/settings/access-tokens) of your Mux account dashboard.
Save the generated token and associated secret to your environment variables.

```env
MUX_TOKEN_ID=b3b7fa9b-efd6-4723-bed2-032b04e61488
MUX_TOKEN_SECRET=YjNiN2ZhOWItZWZkNi00NzIzLWJlZDItMDMyYjA0ZTYxNDg4/l0eZQPmr1S+z+bftAX3Sq
```

## Test Mode

Mux offers a test mode for evaluating their service without incurring charges for storage or streaming.
All videos uploaded in test mode are watermarked and deleted after 24 hours. You can enable test mode
from an environment variable.

```env
MUX_TEST_MODE=true
```
