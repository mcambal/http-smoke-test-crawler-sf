This is default Symfony application installed by symfony/website-skeleton composer package.

I added on top of default installation these files:
- Dockerfile
- docker-compose.yaml
- service.yaml
- Command/HttpSmokeTestCrawlerCommand.php
- added Spatie/Crawler and Swiftmailer composer dependencies to composer.json

This branch is used to illustrate necessary changes I needed to perform regarding Laravel to Symfony switch for Http Smoke Test Crawler 