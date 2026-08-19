<script>
    import {Callout, CodeBlock} from '@hyvor/design/components';
</script>

<h1>Upgrade Guide</h1>

<p>
    This page documents breaking changes and the steps required when upgrading Hyvor Relay between
    versions.
</p>

<h2 id="0-1-0">0.1.0</h2>

<p>
    Starting from <code>0.1.0</code>, email contents are stored in
    a storage backend instead of the database. By default, they are stored on the local filesystem
    under <code>/app/media</code> inside the container. When upgrading, you need to either persist
    this directory with a volume or configure S3-compatible object storage.
</p>

<Callout type="warning">
    Without one of the options below, stored email contents will not survive container restarts.
</Callout>

<h3 id="0-1-0-volume">Option 1: Local filesystem (default)</h3>

<p>
    Add a named volume mounted to <code>/app/media</code> on the <code>relay</code> service in your
    <code>compose.yaml</code>. This is already included in the latest deployment files.
</p>

<CodeBlock
        code={`
services:
  relay:
    # ...
    volumes:
      - relay_media:/app/media

volumes:
  relay_media:
`}
        language="yaml"
/>

<h3 id="0-1-0-s3">Option 2: S3 storage</h3>

<p>
    To use S3-compatible object storage, set
    <code>FILESYSTEM=s3</code> and configure the <code>S3_*</code> variables in your
    <code>.env</code> file.
</p>

<CodeBlock
        code={`
FILESYSTEM=s3
S3_ENDPOINT=
S3_REGION=
S3_KEY=
S3_SECRET=
S3_BUCKET=
`}
        language="yaml"
/>
