# Ingress Comm Automatically send message

### Tips: PHP version



Add the file run.php to the scheduled task or service monitor, for 15 minutes

##### run service
PHP5.4+ needs SQLite3 extensions

run

```php
php run.php
```

---------------------------------------
### Tips: Nodejs version

Add the file run.js to the scheduled task, for 15 minutes

##### run service

Run Version: es6
Required modules: request, SQLite3, tough-cookie-filestore, cheerio

```npm
npm install request --save
npm install sqlite3 --save
npm install cheerio --save
```

Original 'tough-cookie-filestore' problem, please use my modified 'tough-cookie-filestore'

```npm
npm install https://github.com/zishang520/tough-cookie-filestore.git --save
```

run

```nodejs
nodejs run.js
```

### Tips: Python3 version

Add the file run.py to the scheduled task, for 15 minutes

##### run service

Run Version: Python3
Required modules: requests, beautifulsoup4, lxml

```python
pip3 install sqlite3
pip3 install requests
pip3 install beautifulsoup4
pip3 install lxml
```

run

```python
python run.py
```
### Tips: Bin

Linux:

```sh
$ chmod +x Ingress & ./Ingress
```

Windows:

```bat
.\Ingress.exe
```

---------------------------------------
### Configuration Information:

service/data/conf.json.default modify the configuration and renamed conf.json