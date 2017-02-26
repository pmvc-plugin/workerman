[![Latest Stable Version](https://poser.pugx.org/pmvc-plugin/workerman/v/stable)](https://packagist.org/packages/pmvc-plugin/workerman) 
[![Latest Unstable Version](https://poser.pugx.org/pmvc-plugin/workerman/v/unstable)](https://packagist.org/packages/pmvc-plugin/workerman) 
[![Build Status](https://travis-ci.org/pmvc-plugin/workerman.svg?branch=master)](https://travis-ci.org/pmvc-plugin/workerman)
[![License](https://poser.pugx.org/pmvc-plugin/workerman/license)](https://packagist.org/packages/pmvc-plugin/workerman)
[![Total Downloads](https://poser.pugx.org/pmvc-plugin/workerman/downloads)](https://packagist.org/packages/pmvc-plugin/workerman) 

Workerman / A websocket server
===============

## Workerman
   * https://github.com/walkor/Workerman

## How to use
   * https://github.com/pmvc-plugin/workerman/blob/master/demo.php

## Config
VIEW_webSocketUrl='ws://xxx.com/ws'

## Install with Composer
### 1. Download composer
   * mkdir test_folder
   * curl -sS https://getcomposer.org/installer | php

### 2. Install by composer.json or use command-line directly
#### 2.1 Install by composer.json
   * vim composer.json
```
{
    "require": {
        "pmvc-plugin/workerman": "dev-master"
    }
}
```
   * php composer.phar install

#### 2.2 Or use composer command-line
   * php composer.phar require pmvc-plugin/workerman

