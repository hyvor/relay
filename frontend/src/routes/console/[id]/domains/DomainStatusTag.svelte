<script lang="ts">
    import { Tag } from '@hyvor/design/components';

    interface Props {
        status: 'pending' | 'active' | 'warning' | 'suspended';
        size?: 'x-small' | 'small' | 'medium' | 'large';
    }

    let { status, size = 'small', }: Props = $props();
    let color: 'default' | 'accent' | 'green' | 'red' | 'blue' | 'orange' = $state('default');

    $effect(() => {
        switch (status) {
            case "active":
                color = 'green';
                break;
            case "warning":
                color = 'orange';
                break;
            case "suspended":
                color = 'red';
                break;
            default:
                color = 'default';
        }
    })
</script>

<Tag
    size={size}
    color={color}
>
    {status}
</Tag>
