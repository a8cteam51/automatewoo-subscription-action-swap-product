# AutomateWoo Subscription Action Swap Product

Extends the functionality of AutomateWoo with a custom action to swap products on subscriptions. 

## Usage

This action is intended to be used on manual workflows to swap out products on subscriptions in a store, on a 1:1 basis. So, when looking at the line items of an existing subscription, `Product A x3` becomes `Product B x3`. 

## Notes
- This doesn't work for Bundled Products. Let me know if this is something you want, and we can add this feature.
- There is currently no advanced math or formulae available for this action, it just does a 1:1 swap.
- This will not change price or quantity of line item, or any other characteristics of the subscription. It simply swaps out the product. Future update idea: Add a checkbox for "Recalculate totals?"

## Support

This plugin is provided without any support or guarantees of functionality. If you'd like to contribute, feel free to open a PR on this repo. Please test thoroughly before deploying to a production site.
