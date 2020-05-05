Uploading Documents
-------------------

Uploaded documents are compiled by running the Coursebuilder software in a docker container. Since allowing access to docker is practically giving root to a user this must be handled carefully. The current test setup on our coursbuilder LTI server is as follows:

Relevant Users
--------------

### Privileged

 * root
 * programs - Owns the files in the LTI tool's INSTALLDIR and is allowed to run docker by addition to the "docker" group.
 
### Unprivileged

 * webedit - This user can write to CONTENTDIR, this allows automatic uploads using something like gitlab-ci. This user should not be allowed to edit anything else in INSTALLDIR.
 * www-data - The user running apache facing the public web. Actions that this user can do should be carefully considered. This user should not be allowed to edit arbitrary files in INSTALLDIR.

Relevant Groups
---------------

 * docker - docker:x:105:programs
 * www-data - www-data:x:33:webedit,programs

Process
-------
The processing of files is as follows.

First, a file is uploaded to the UPLOADDIR via PHP. This is performed by the www-data user. On upload a GUID is generated for the uploaded bundle of files. Zip files are extracted in-place.

Next, PHP starts the process script. The script runs partially in a docker container, but we don't want to give access to the docker daemon to www-data. So, sudo is used to start the script as the programs user. Sudo should be configured so that www-data can start the process script (and no other command) as the programs user (and no other user).

The process script performs the following actions:
  * Copies the upload directory to the process directory (we work on a copy of the uploaded files because we generate/edit the config.yml before running coursebuilder).

  * Create or edit config.yml for the files in the process directory. For raw .tex or .md file upload a standalone course.yml is created. For a coursebuilder bundle config.yml already exists, it is modified to inject the correct content path and generated content GUID.

  * Run coursebuilder in a container for the prepared course.yml in the process directory: sudo -u programs ./process.sh <args>  > PROCESSDIR/logs/<guid>.log

  * If successful, copies the result from the build directory into the CONTENTDIR and sets the permissions to 775 programs:www-data

  * When finished, deletes the copy of the uploaded files in the PROCESSDIR, running "make clean" in the container first.

The output of the process script is written to a log file at PROCESSDIR/logs/<guid>.log by PHP. This is written as the www-data user and so it must have permission to make files there.

The upload directory is currently retained. This should be cleaned at some point to avoid filling the disk. 

Sudoers setup
-------------
Ensure that www-data is allowed to run the process script as the programs user:
www-data ALL = (programs) NOPASSWD: <PROCESSDIR>/process.sh

Directory Permissions
---------------------
The current permission setup is as follows. Really, www-data should not be allowed to write to CONTENTDIR and this should be changed to some other group in which programs and webedit are both members.

 * INSTALLDIR - 755 programs:programs
 * CONTENTDIR - 775 programs:www-data
 * UPLOADDIR  - 775 programs:www-data
 * PROCESSDIR/logs - 775 programs:www-data

Future work
-----------
Much of this process might be handled better by running rootless docker. That might be safe enough to allow www-data to run the processing script directly, rather than invoking the programs user.

The uploads directory should be cleaned at some point to avoid filling the disk. PHP could do this, running as the www-data, or perhaps a cron job should be set up to periodically remove the upload directory. At the moment uploads are not deleted so that we can inspect failed builds.
