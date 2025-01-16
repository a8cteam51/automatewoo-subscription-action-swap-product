| :exclamation:  This is a public repository |
|--------------------------------------------|

# AutomateWoo Subscription Action Swap Product

Extends the functionality of AutomateWoo with a custom action to swap products on subscriptions. 

THIS BRANCH IS CUSTOMIZED FOR NOVOS TO SUPPORT FLAVORCLOUD AND ROUTE.COM CALCULATIONS

## Usage

This action is intended to be used on manual workflows to swap out products on subscriptions in a store, on a 1:1 basis. So, when looking at the line items of an existing subscription, `Product A x3` becomes `Product B x3`. 

Check the "Recalculate Totals?" checkbox if you want the subscription price to reflect the changes made to the products. Otherwise, no changes are made to any prices on the subscription.

## Notes
- This doesn't work for Bundled Products. Let me know if this is something you want, and we can add this feature.
- There is currently no advanced math or formulae available for this action, it just does a 1:1 swap.
- This will not change the quantity of line item, or any other characteristics of the subscription. It simply swaps out the product. Prices will only be recalculated if the "Recalculate Totals?" checkbox is checked.

## Support

This plugin is provided without any support or guarantees of functionality. If you'd like to contribute, feel free to open a PR on this repo. If you have a request, please open an issue.

> [!WARNING]  
> Please test thoroughly before deploying to a production site.
