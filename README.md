<p align="center">
    <a href="https://updeskcrm.com">
        <picture>
            <source media="(prefers-color-scheme: dark)" height="100" srcset="packages/Webkul/Admin/src/Resources/assets/images/dark-logo.svg">
            <source media="(prefers-color-scheme: light)" height="100" srcset="packages/Webkul/Admin/src/Resources/assets/images/logo.svg">
            <img alt="UpDesk CRM" height="100" src="packages/Webkul/Admin/src/Resources/assets/images/logo.svg">
        </picture>
    </a>
</p>

<p align="center">
<a href="https://packagist.org/packages/krayin/laravel-crm"><img src="https://poser.pugx.org/krayin/laravel-crm/d/total.svg" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/krayin/laravel-crm"><img src="https://poser.pugx.org/krayin/laravel-crm/v/stable.svg" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/krayin/laravel-crm"><img src="https://poser.pugx.org/krayin/laravel-crm/license.svg" alt="License"></a>
</p>


![enter image description here](https://raw.githubusercontent.com/krayin/temp-media/master/dashboard.png)

## Topics

1. [Introduction](#introduction)
2. [Documentation](#documentation)
3. [Requirements](#requirements)
4. [Installation & Configuration](#installation-and-configuration)
4. [Docker Installation](https://devdocs.updeskcrm.com/2.0/introduction/docker.html)
5. [UpDesk Cloud System](#krayin-cloud-hosting)
6. [License](#license)
7. [Security Vulnerabilities](#security-vulnerabilities)

### Introduction

[UpDesk CRM](https://updeskcrm.com) is a hand tailored CRM framework built on some of the hottest opensource technologies
such as [Laravel](https://laravel.com) (a [PHP](https://secure.php.net/) framework) and [Vue.js](https://vuejs.org)
a progressive Javascript framework.

**Free & Opensource Laravel CRM solution for SMEs and Enterprises for complete customer lifecycle management.**

**Read our documentation: [UpDesk CRM Docs](https://devdocs.updeskcrm.com/)**

**We also have a forum for any type of concerns, feature requests, or discussions. Please visit: [UpDesk CRM Forums](https://forums.updeskcrm.com/)**

# Visit our live [Demo](https://demo.updeskcrm.com)

<a href="javascript:void();">
    <img class="flag-img" src="https://raw.githubusercontent.com/krayin/temp-media/master/visit-our-live-demo.png" alt="Chinese" width="100%">
</a>

It packs in lots of features that will allow your E-Commerce business to scale in no time:

-   Descriptive and Simple Admin Panel.
-   Admin Dashboard.
-   Custom Attributes.
-   Built on Modular Approach.
-   Email parsing via Sendgrid.
-   Check out [these features and more](https://updeskcrm.com/features/).

**For Developers**:
Take advantage of two of the hottest frameworks used in this project -- Laravel and Vue.js -- both of which have been used in UpDesk CRM.

### Documentation

#### UpDesk Documentation [https://devdocs.updeskcrm.com](https://devdocs.updeskcrm.com)

### Requirements

-   **SERVER**: Apache 2 or NGINX.
-   **RAM**: 3 GB or higher.
-   **PHP**: 8.3 or higher
-   **Composer**: 2.5 or higher
-   **For MySQL users**: 8.0.32 or higher.
-   **For MariaDB users**: 11.4 LTS or higher (11.8 LTS recommended).

### Installation and Configuration

##### Execute these commands below, in order

```
composer create-project
```

-   Find **.env** file in root directory and change the **APP_URL** param to your **domain**.

-   Also, Configure the **Mail** and **Database** parameters inside **.env** file.

```
php artisan krayin-crm:install
```

**To execute UpDesk**:

##### On server:

Warning: Before going into production mode we recommend you uninstall developer dependencies.
In order to do that, run the command below:

> composer install --no-dev

```
Open the specified entry point in your hosts file in your browser or make an entry in hosts file if not done.
```

##### On local:

```
php artisan route:clear
php artisan serve
```


**How to log in as admin:**

> _http(s)://example.com/admin/login_

```
email:admin@example.com
password:admin123
```

### UpDesk Cloud Hosting

[UpDesk CRM Cloud Hosting](https://updeskcrm.com/crm-cloud-hosting) is a fully managed hosting solution where our team sets up, secures, and configures your UpDesk CRM on reliable infrastructure.

Get a ready-to-use CRM on your own domain, without manual installation or infrastructure complexity, and focus on growing your business while we handle the technology.

![UpDesk CRM Cloud Hosting](https://raw.githubusercontent.com/krayin/temp-media/master/cloud_hosting.png)

### UpDesk CRM Multi Tenant SaaS

[UpDesk CRM Multi Tenant SaaS](https://updeskcrm.com/extensions/krayin-crm-multi-tenant-saas-extension/) UpDesk Multitenant SaaS is a Laravel-based CRM solution that allows multiple businesses (tenants) to use a single application instance while keeping their data isolated and secure.

![enter image description here](https://raw.githubusercontent.com/krayin/temp-media/master/krayin-saas.png)

### WhatsApp CRM Integration

[UpDesk CRM WhatsApp](https://updeskcrm.com/extensions/krayin-crm-whatsapp-extension/) Extension enables the store administrator to generate leads via their WhatsApp number.

![enter image description here](https://raw.githubusercontent.com/krayin/temp-media/master/krayin-crm-whatsapp-integration.png)

### VoIP CRM Integration

[UpDesk CRM VoIP](https://updeskcrm.com/extensions/krayin-crm-voip/) extension allows the user to make Trunk calls over a broadband Internet connection and the user can also perform Inbound routes.

![enter image description here](https://raw.githubusercontent.com/krayin/temp-media/master/krayin-voip.png)

### License

UpDesk CRM is a fully open-source CRM framework which will always be free under the [MIT License](https://github.com/krayin/laravel-crm/blob/2.1/LICENSE).

### Security Vulnerabilities

Please don't disclose security vulnerabilities publicly. If you find any security vulnerability in UpDesk CRM then please email us: sales@updesk.com.
