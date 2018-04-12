# Linda Coin Control Script

### Install Coin Control Script using Git & [Composer](https://getcomposer.org/)

```sh
git clone https://github.com/amassaro/linda-coin-control
cd linda-coin-control
composer install
```

### Configuration file (config.json)

- Rename config.default.json to config.json
- Edit settings as desired

#### Sample config defaults

```json
{
    "linda_path": "/usr/local/bin/Lindad",
    "db": {
        "host": "",
        "user": "root",
        "pass": "",
        "database": ""
    },
    "email": {
        "host": "",
        "port": 587,
        "secure": "tls",
        "user": "",
        "pass": ""
    },
    "notify_email": "",
    "wallet_id": 0,
    "wallet_address": "",
    "trans_amount": 0.0001
}
```

### Install local MySQL tracking database

```sh
php linda_coin_control.php --install
```

### Create cron job (runs every 5 minutes)

```sh
(crontab -l 2>/dev/null; echo "*/5 * * * * /usr/bin/php /path/to/linda_coin_control.php >/dev/null 2>&1") | crontab -
```

### Credits

- Original script by Chris @ [Turnkey Web Tools](http://www.turnkeywebtools.com)
- This GIT repo was created by me to allow further customization and configuration as well as an easier mechanism to install the tool.

### Donations
<dl>
    <dt>Chris' Wallet Address</dt>
    <dd>LQJ6aShBPDieLcYUZerkhrChefqMTLvR8c</dd>
    <dt>My Wallet Address</dt>
    <dd>Li1VayEzgTmMup7txFtw6CPRzGQyRuY1bm</dd>
</dl>

### Linda Coin Discord

https://discord.gg/sPxtM5n