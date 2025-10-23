<script>
import { Accordion } from '@hyvor/design/components';
import IconLayers from '@hyvor/icons/IconLayers';
</script>

<h3 id="faq">Frequently Asked Questions</h3>

<div class="faq">
    <Accordion title="Is Hyvor Relay free to use?" icon={IconLayers}>
        Hyvor Relay is open-source and free to self-host. You can find the source code on our
        <a href=" "></a>
    </Accordion>

    <Accordion title="How can I self-host Hyvor Relay?" icon={IconLayers}>
        You can follow the self-hosting guide available in our
        <a href="/docs/self-hosting/installation">documentation</a> to set up Hyvor Relay on your own
        server.
    </Accordion>

    <Accordion title="What are the system requirements for self-hosting?" icon={IconLayers}>
        The system requirements for self-hosting Hyvor Relay depend on your expected email volume
        and usage patterns. Please refer to our
        <a href="/docs/self-hosting/requirements">documentation</a> for detailed information.
    </Accordion>

    <Accordion title="Where can I find the API documentation?" icon={IconLayers}>
        The API documentation for Hyvor Relay is available in our
        <a href="/docs/api">documentation</a>. It provides detailed information on how to use the
        email API and other features.
    </Accordion>

    <!--mention about hyvor post-->
    <Accordion title="FAQ, continued... about Hyvor Post" icon={IconLayers}>
        Hyvor Post is a separate product focused on email marketing and newsletters. For more
        information about Hyvor Post, please visit
        <a href="https://post.hyvor.com" style="text-decoration: var(--accent)">post.hyvor.com</a>.
    </Accordion>
</div>

<style>
    h3 {
        text-align: center;
        margin-top: 80px;
        font-size: 32px;
    }
    .faq {
        margin-top: 40px;
        width: 800px;
        gap: 20px;
        max-width: 100%;
        display: flex;
        flex-direction: column;
        justify-content: center;
        margin: auto;
    }

   </style>

