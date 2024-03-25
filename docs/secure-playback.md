# Secure Playback

To control who can view your videos and when, you can apply one of two playback
policies to your videos. Learn more in the Mux docs on
[Secure Video Playback](https://docs.mux.com/guides/secure-video-playback).

- **Public**: can be watched anywhere without any restrictions
- **Signed**: requires a valid server-signed token to gain access

## Signed Playback Urls

Creating secure playback urls requires three things:

- Switch the **default playback policy** to `signed`
- Create a valid **signing key** to validate the playback url on the server
- Set an **expiration time** that determines how long the signed url is valid

## Playback Policy

Videos uploaded to Mux by this addon default to a `public` playback policy. To enable
secure playback, switch it to `signed` in your environment variables.

```env
MUX_PLAYBACK_POLICY=signed
```

## Signing Key

Create a new Signing Key in the [Signing Key settings](https://dashboard.mux.com/settings/signing-keys)
of your Mux account dashboard. Save the generated key id and associated private
key to your environment variables.

```env
MUX_SIGNING_KEY_ID=RWhsT4SymRo7GDKjUVVAlfbSXbfwB9Is5PdAxcz5Iic
MUX_SIGNING_PRIVATE_KEY=LS0tLS1CRUdJTiBSU0EgUFJJVkFURSBLR (...)
```

## Expiration Time

You must define an expiration time for generated playback urls in your environment variables.
Any requests made to access a video past the expiration time are denied.

```env
MUX_SIGNED_URL_EXPIRATION="2 weeks"
```

> [!DANGER] Note about caching
> If you're caching the frontend output of your site, make sure to either wrap any videos in a
> [nocache](https://statamic.dev/tags/nocache) tag or set a playback expiration time
> that is **longer** than your static site cache expiration time. Otherwise,
> you'll risk inaccessible videos.

## Playback URLs

If you've configured the default playback policy and added a valid signing key, any new videos uploaded
to Mux will now stream using secure playback urls as long as you're using the provided
[Antlers tags](/tags) of this addon.
