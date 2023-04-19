# Shopware Plugin: Automatic status emails

Using this plugin notification emails for order- and payment status changes via backend or the API can be sent automatically. The status to send emails for are configurable. Manual sending of status emails is deactivated for selected status to avoid duplicates. Since version 1.4.0 customer groups can be selected to limit automatic sending of emails to those groups. Since version 1.5.0 additional status can be selected that trigger sending of emails in combination with assignment of a shipment tracking code. The order of assignment (tracking code first or status first) is not taken into account.

**Notice:** This plugin is designed to work with the API primarily and is not compatible with batch processing via backend.

The plugin is compatible with Shopware version 5.2.0 and greater. It is not compatible with Shopware 6.