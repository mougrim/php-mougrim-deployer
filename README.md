# php-mougrim-deployer
Deployment tool. This is not stable version.
## Benefits
Php-mougrim-deployer gives the following benefits:

* seamless deployment of source code;
* seamless rolled back to a previous version.

## How to use
### Prepare
```sh
# Clone deployer into some path:
cd /some/path
git clone git@github.com:mougrim/php-mougrim-deployer.git # Clone php-mougrim-deployer
export PATH=$PATH:/some/path/php-mougrim-deployer/bin     # Add bin directory to PATH

# Clone your application into some path (not application path):
cd /not/application/path
git clone git@github.com:user/my-application.git
cd my-application
```
### Deploy
```sh
# Deploy your application:
mougrim-deployer.php \
	--tag=v1.3.2 \                                  # git tag - version of release
	--application-path=/path/to/folder/to/deploy \  # path to application
	--user=myapplicationuser \                      # web server user
	--group=myapplicationgroup \                    # web server group
	--pre-switch-script=bin/pre-switch.php \       # before switch version script (optional)
	--post-switch-script=bin/post-switch.php       # after switch version scrpit (optional)
```
You can create sh-script (example bin/my-deploy-script.sh), for simplify deploy:
```sh
#!/bin/sh
mougrim-deployer.php \
	--tag=$1 \
	--application-path=/path/to/folder/to/deploy \
	--user=myapplicationuser \
	--group=myapplicationgroup \
	--pre-switch-script=bin/pre-switch.php \
	--post-switch-script=bin/post-switch.php
```
Use:
```sh
bin/my-deploy-script.sh v1.3.2
```
### Result:
Versions list:
```sh
user@user-pc:~$ ls -lah /path/to/folder/to/deploy/versions/
total 28K
drwxrwxr-x 7 myapplicationuser myapplicationgroup 4,0K jul  15 09:13 .
drwxrwxr-x 4 myapplicationuser myapplicationgroup 4,0K jul  15 09:13 ..
lrwxrwxrwx 1 myapplicationuser myapplicationgroup   52 jul  15 09:13 current -> /path/to/folder/to/deploy/versions/v1.3.2
drwxr-xr-x 7 myapplicationuser myapplicationgroup 4,0K jul  14 22:15 v0.0.1
drwxr-xr-x 7 myapplicationuser myapplicationgroup 4,0K jul  14 09:30 v0.0.2
drwxr-xr-x 7 myapplicationuser myapplicationgroup 4,0K jul  14 22:17 v0.0.3
drwxr-xr-x 7 myapplicationuser myapplicationgroup 4,0K jul  15 09:02 v0.0.4
drwxr-xr-x 7 myapplicationuser myapplicationgroup 4,0K jul  15 09:13 v1.3.2
```
Symlink current is point to current version.

Yur can switch to previous version:
```sh
sudo -u 'myapplicationuser' ln -sfT '/home/sites/manufacturer-test-deploy/versions/v0.0.4' '/home/sites/manufacturer-test-deploy/versions/current'
```

Version content:
```sh
user@user-pc:~$ ls -lah /path/to/folder/to/deploy/versions/v1.3.2/
total 32K
drwxr-xr-x 7 myapplicationuser myapplicationgroup 4,0K jul  15 09:13 .
drwxrwxr-x 7 myapplicationuser myapplicationgroup 4,0K jul  15 09:13 ..
drwxr-xr-x 3 myapplicationuser myapplicationgroup 4,0K jul  15 09:06 protected
drwxr-xr-x 2 myapplicationuser myapplicationgroup 4,0K jul  15 09:06 bin
-rw-r--r-- 1 myapplicationuser myapplicationgroup    8 jul  15 09:06 .gitignore
lrwxrwxrwx 1 myapplicationuser myapplicationgroup   41 jul  15 09:13 logs -> /path/to/folder/to/deploy/logs
drwxr-xr-x 9 myapplicationuser myapplicationgroup 4,0K jul  15 09:06 public
```
Symlink logs and directory /path/to/folder/to/deploy/logs created in bin/pre-switch.php (your script).

Application content:
```sh
user@user-pc:~$ ls -lah /path/to/folder/to/deploy
total 32K
drwxrwxr-x 4 myapplicationuser myapplicationgroup 4,0K jul  15 09:13 .
drwxr-xr-x 6 myapplicationuser myapplicationgroup 4,0K jul  13 22:54 ..
lrwxrwxrwx 1 myapplicationuser myapplicationgroup   62 jul  14 09:29 protected -> /path/to/folder/to/deploy/versions/current/protected
lrwxrwxrwx 1 myapplicationuser myapplicationgroup   58 jul  14 09:29 bin -> /path/to/folder/to/deploy/versions/current/bin
lrwxrwxrwx 1 myapplicationuser myapplicationgroup   64 jul  14 09:29 .gitignore -> /path/to/folder/to/deploy/versions/current/.gitignore
drwxr-xr-x 2 myapplicationuser myapplicationgroup 4,0K jul  15 09:13 logs
drwxrwxr-x 7 myapplicationuser myapplicationgroup 4,0K jul  15 09:13 versions
lrwxrwxrwx 1 myapplicationuser myapplicationgroup   57 jul  14 09:37 public -> /path/to/folder/to/deploy/versions/current/public
```
In this example web root in /path/to/folder/to/deploy/public.
