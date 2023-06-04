# 📦️ OFFLINE.Boxes (Free Version)

This is the free version of the [OFFLINE.Boxes](https://octobercms.com/plugin/offline-boxes) plugin for October CMS.

You can download and install this version for free in any October CMS project.

The free version provides the basic functionality to create and manage content with Boxes.

## 🚀 Installation

Install the plugin using Composer.

```
composer require offline/oc-boxes-free-plugin
php artisan october:migrate
```

Follow the [integration guide from the documentation](https://docs.boxes.offline.ch/getting-started/installation.html#integration)
to finish the installation.

## ⚖️ Feature Comparison

Boxes is available in a free and a [paid version](https://octobercms.com/plugin/offline-boxes).
The following table compares the features of both versions:

| Feature                                                                                                               | Pro | Free |
|-----------------------------------------------------------------------------------------------------------------------|-----|------|
| [Boxes Editor](https://docs.boxes.offline.ch/concepts/box-editor.html)                                                | ✅   | ✅    |
| [Boxes Editor as form widget in Tailor/Custom Plugins](https://docs.boxes.offline.ch/use-cases/usage-in-plugins.html) | ✅   | ❌    |
| [Revisions](https://docs.boxes.offline.ch/concepts/revisions.html)                                                    | ✅   | ❌    |
| [References](https://docs.boxes.offline.ch/concepts/box-references.html)                                              | ✅   | ❌    |
| [Multisite Support](https://docs.boxes.offline.ch/use-cases/multisite.html)                                           | ✅   | ❌    |
| [Exporting and Importing](https://docs.boxes.offline.ch/use-cases/export-import.html)                                 | ✅   | ❌    |
| [Page Templates](https://docs.boxes.offline.ch/use-cases/page-templates.html)                                         | ✅   | ❌    |

## 📕 Documentation

The documentation for Boxes can be found on [docs.boxes.offline.ch](https://docs.boxes.offline.ch/).

Note that the documentation is for the paid version of Boxes. The free version does not include all features
described in the documentation. Pro features are marked with a "Pro" badge.

## ❓️ FAQ

### Why are you releasing a free version?

We want to make Boxes available to as many people as possible. The free version is a great way to get started with Boxes
and to evaluate it in your projects.

We believe that Boxes is a great tool for many developers and agencies. If you like it, please consider buying the
paid version to support the development of Boxes.

### Can I upgrade to the paid version later?

Yes, you can upgrade to the paid version at any time. All your content will be preserved. For instructions on how to
upgrade, please see below.

### Can I use the free version in production?

Yes, you can use the free version in any project. The free version is fully functional and does not contain any
artificial limitations. To benefit from the additional power-user features of the paid version, you can upgrade at any time.

### Do you accept pull requests/issue reports?

No, we do not accept pull requests or issue reports for the free version. If you want to contribute to Boxes, please
consider buying the paid version. For paying customers we offer a [GitHub repository](https://github.com/OFFLINE-GmbH/oc-boxes-support) where you can submit feature
requests and bug reports.

## ⬆️ Upgrading from the free version to the paid version

You can upgrade from the free version to the paid version at any time. Please back up your database before doing so to prevent data loss.

First uninstall the free version:

```
composer remove offline/oc-boxes-free-plugin
```

Then follow [the installation instructions for the paid version](https://docs.boxes.offline.ch/getting-started/installation.html).




## License

The free version of Boxes is released under a proprietary license. Please see [License File](LICENSE) for more information.
