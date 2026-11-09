# Upgrading to 0.1.0

## Required: Configuration for email content storage

Starting from version 0.1.0, email contents are no longer saved in the database. Without the following changes, **email content will not survive container restarts**. We made this change because storing email contents in the database became a bottleneck for storage and scalability. A dedicated storage like S3 can handle that better. For most small deployments, we also support storing email contents in the local filesystem, without having to set up S3.

By default, email contents will be saved in the `/app/media` directory within the container. You can either configure a Docker volume to persist the contents or configure an external S3 bucket to store the contents.

### Option 1: Configure a Docker Volume

Setting up a Docker volume is the easiest way and we recommend this option for most users. Update your `compose.yaml` to include the following volume configuration:

```yaml
services:
    relay:
        # ...
        volumes:
            - relay_media:/app/media

volumes:
    relay_media:
```

Note: our `deploy/easy/compose.yaml` and `deploy/prod/compose.yaml` files have already been updated to include this volume configuration.

### Option 2: Configure an S3 Bucket

If your instance sends a large number of emails, it might make sense to use a dedicated S3-compatible storage (AWS S3, DigitalOcean Spaces, MinIO, etc.) to store email contents. To do this, set up the following environment variables in your `compose.yaml`:

```txt
FILESYSTEM=s3
S3_ENDPOINT=
S3_REGION=
S3_KEY=
S3_SECRET=
S3_BUCKET=
```

### Notes on email content storage

- Migration [Version20260703120538](https://github.com/hyvor/relay/blob/main/backend/migrations/Version20260703120538.php) will automatically move existing email contents from the database to the configured storage. Make sure to configure the storage **before** running the new version.
- To switch from filesystem to S3 or vice versa, you can simply copy the contents from one storage to another and update the environment variables accordingly.

---

## Other Changes in 0.1.0

- API keys require specifying allowed IP addresses / CIDR ranges if the `sends.send` scope is used. Previously, it rejected ranges that were too broad. Since 0.1.0, it will allow broader ranges with a warning instead. This allows use cases where broad ranges are necessary, such as calling Hyvor Relay from an edge network server.
- 1:1 [NAT configuration](/hosting/setup#nat) is now supported to run Hyvor Relay behind a private network.
