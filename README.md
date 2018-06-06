# SINGLE-SIGN-ON (SSO)

    https://github.com/danhammari/sso

This repository is built around a docker compose file that will create sso.test subdomains which implement various SSO technologies.

# INSTRUCTIONS

1. Install Docker on your host machine

    https://www.docker.com/community-edition

2. Clone this git repository to your host machine

    git clone git@github.com:danhammari/sso.git

3. Add the following DNS entries to your /etc/hosts file:

    #------------------------------------
    # DOCKER SSO TEST
    #------------------------------------
    127.0.0.1  facebook.sso.test
    127.0.0.1  google.sso.test
    127.0.0.1  lti.sso.test
    127.0.0.1  samlidp.sso.test
    127.0.0.1  samlsp.sso.test
    127.0.0.1  www.sso.test
    127.0.0.1  sso.test

4. In a terminal window, go to the directory where you cloned this repository and run the following command:

	docker-compose up

5. You should now be able to access the following domains in your web browser:

    https://facebooks.sso.test
    https://google.sso.test
    https://lti.sso.test
    https://samlidp.sso.test
    https://samlsp.sso.test
    https://www.sso.test

6. To shut down the running docker instances at any time, go to your terminal window and press control-c to interrupt docker. Then run the following command to clean up all docker connections:

    docker-compose down

