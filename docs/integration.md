---
name: wp-module-pls
title: Integration
description: How the module registers and integrates.
updated: 2025-03-18
---

# Integration

The module registers with the Newfold Module Loader via bootstrap.php. wp-module-installer and other modules use it for plugin license provisioning and validation via the PLS API. See [dependencies.md](dependencies.md).
