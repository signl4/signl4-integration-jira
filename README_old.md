# SIGNL4 Integration with Jira
PHP script for two-way integration of SIGNL4 with Atlassian Jira.

## Motivation
Jira is an issue tracking product developed by Atlassian. The integration with SIGNL4 is quite straight forward. Jira offers a REST API for this. When you create a new issue in Jira you can configure to trigger an HTTP request that as a result sends an alert via SIGNL4.
However, it would be great to update the issue in Jira when the alert has been acknowledged in SIGNL4. This two-way integration is described in the following.
                        
## Basic Idea
For sending the acknowledgement information back to Jira we would need to match the Jira ID with the ID of SIGNL4. This is how it works with a simple PHP script:
 
Jira -> PHP Script:<br/>
When a Jira issue is created you trigger a webhook to be sent to the URL where your PHP scrips is running. You can configure this easily in the webhook section in the Jira settings.
 
PHP -> SIGNL4:<br/>
Upon receiving the Jira issue information the PHP script will send an HTTP request to SIGNL4 in order to trigger the alert. The PHP script will pass the Jira issue ID (along with some additional information) to SIGNL4 in the external ID field (X-S4-ExternalID). This is used as a reference in order to match it later. As a response the PHP script will get the event ID of the SIGNL4 event.
 
SIGNL4 -> PHP Script:<br/>
In the SIGNL4 portal you configure the outbound webhook that will be called when a user acknowledges an alert in SIGNL4. You can do so under Developer -> Webhooks.
The PHP script will receive the acknowledgement request where the SIGNL4 original external ID (X-S4-ExternalID) is contained again. It contains the corresponding Jira issue ID and then sends back the update request to Jira.
 
PHP Script -> Jira:<br/>
In Jira the issue is then updates accordingly.

## Implementation
The PHP script is attached. You would need to add your Jira credentials here in order to be able to use the REST API.
You can either use your own Web server or try out a service like heroku.com.
 
## Extended Functionality
The above example is quite straight forward and additional functionality like matching users, supporting annotations, etc. is possible.
