## Production Deployment Instructions

### Ansible Setup

```
cp inventory.example.ini inventory.ini
```

Add your server's IP address and SSH user in `inventory.ini`.

### Docker Stack Deployment

```bash
docker stack deploy -c compose.yaml relay
```
