{**
 * templates/workflow/workflow.tpl
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display the workflow tab structure.
 *}

{strip}
	{assign var=pageTitleTranslated value=$submission->getShortAuthorString()|strip_unsafe_html|concat:"; ":$submission->getLocalizedTitle()|strip_unsafe_html}
	{include file="common/header.tpl" suppressPageTitle=true}
{/strip}

<div class="pkp_page_content">
	{assign var="uuid" value=""|uniqid|escape}
	<div id="workflow-{$uuid}" class="pkpWorkflow">
		<pkp-header :is-one-line="true" class="pkpWorkflow__header">
			<h1 class="pkpWorkflow__identification">
				<badge
					v-if="submission.status === getConstant('STATUS_PUBLISHED')"
					class="pkpWorkflow__identificationStatus"
					:is-success="true"
				>
					{translate key="publication.status.published"}
				</badge>
				<badge
					v-else-if="submission.status === getConstant('STATUS_SCHEDULED')"
					class="pkpWorkflow__identificationStatus"
					:is-primary="true"
				>
					{translate key="publication.status.scheduled"}
				</badge>
				<span class="pkpWorkflow__identificationId">{{ submission.id }}</span>
				<span class="pkpWorkflow__identificationDivider">/</span>
				<span class="pkpWorkflow__identificationAuthor">
					{{ currentPublication.authorsStringShort }}
				</span>
				<span class="pkpWorkflow__identificationDivider">/</span>
				<span class="pkpWorkflow__identificationTitle">
					{{ localizeSubmission(currentPublication.fullTitle, currentPublication.locale) }}
				</span>
			</h1>
			<template slot="actions">
				<pkp-button
					element="a"
					:label="submission.status === getConstant('STATUS_PUBLISHED') ? i18n.view : i18n.preview"
					:href="submission.urlPublished"
				></pkp-button>
				<pkp-button
					label="{translate key="editor.activityLog"}"
					ref="activityButton"
					@click="openActivity"
				></pkp-button>
				<pkp-button
					label="{translate key="editor.submissionLibrary"}"
					ref="library"
					@click="openLibrary"
				></pkp-button>
			</template>
		</pkp-header>
		<tabs default-tab="workflow">
			<tab id="workflow" label="{translate key="manager.workflow"}">
				<script type="text/javascript">
					// Initialize JS handler.
					$(function() {ldelim}
						$('#submissionWorkflow').pkpHandler(
							'$.pkp.pages.workflow.WorkflowHandler'
						);
					{rdelim});
				</script>

				<div id="submissionWorkflow" class="pkp_submission_workflow">

					{include file="controllers/notification/inPlaceNotification.tpl" notificationId="workflowNotification" requestOptions=$workflowNotificationRequestOptions}

					{capture assign=submissionProgressBarUrl}{url op="submissionProgressBar" submissionId=$submission->getId() stageId=$stageId contextId="submission" escape=false}{/capture}
					{load_url_in_div id="submissionProgressBarDiv" url=$submissionProgressBarUrl}

				</div>
			</tab>
			{if $canAccessPublication}
				<tab id="publication" label="{translate key="submission.publication"}">
					<div class="pkpPublication" ref="publication" aria-live="polite">
						<pkp-header class="pkpPublication__header">
							<span class="pkpPublication__status">
								<strong>{{ i18n.status }}</strong>
								<span v-if="workingPublication.status === getConstant('STATUS_QUEUED') && workingPublication.id === currentPublication.id" class="pkpPublication__statusUnpublished">{translate key="publication.status.unscheduled"}</span>
								<span v-else-if="workingPublication.status === getConstant('STATUS_SCHEDULED')">{translate key="submissions.scheduled"}</span>
								<span v-else-if="workingPublication.status === getConstant('STATUS_PUBLISHED')" class="pkpPublication__statusPublished">{translate key="publication.status.published"}</span>
								<span v-else class="pkpPublication__statusUnpublished">{translate key="publication.status.unpublished"}</span>
							</span>
							<span v-if="submission.publications.length > 1" class="pkpPublication__version">
								<strong tabindex="0">{{ i18n.version }}</strong> {{ workingPublication.id }}
								<dropdown
									class="pkpPublication__versions"
									label="{translate key="publication.version.all"}"
									:is-link="true"
									submenu-label="{translate key="common.submenu"}"
								>
									<ul>
										<li v-for="publication in submission.publications" :key="publication.id">
											<button
												class="pkpDropdown__action"
												:disabled="publication.id === workingPublicationId"
												@click="setWorkingPublicationId(publication)"
											>
												{{ publication.id }} /
												<template v-if="publication.status === getConstant('STATUS_QUEUED') && publication.id === currentPublication.id">{translate key="publication.status.unscheduled"}</template>
												<template v-else-if="publication.status === getConstant('STATUS_SCHEDULED')">{translate key="submissions.scheduled"}</template>
												<template v-else-if="publication.status === getConstant('STATUS_PUBLISHED')">{translate key="publication.status.published"}</template>
												<template v-else>{translate key="publication.status.unpublished"}</template>
											</button>
										</li>
									</ul>
								</dropdown>
							</span>
							{if $canAccessProduction}
								<template slot="actions">
									<pkp-button
										v-if="workingPublication.status === getConstant('STATUS_QUEUED')"
										ref="publish"
										:label="submission.status === getConstant('STATUS_PUBLISHED') ? i18n.publish : i18n.schedulePublication"
										@click="openPublish"
									></pkp-button>
									<pkp-button
										v-else-if="workingPublication.status === getConstant('STATUS_SCHEDULED')"
										label="{translate key="publication.unschedule"}"
										:is-warnable="true"
										@click="openUnpublish"
									></pkp-button>
									<pkp-button
										v-else-if="canCreateNewVersion"
										ref="createVersion"
										label="{translate key="publication.createVersion"}"
										@click="createVersion"
									></pkp-button>
								</template>
							{/if}
						</pkp-header>
						<div
							v-if="workingPublication.status === getConstant('STATUS_PUBLISHED')"
							class="pkpPublication__versionPublished"
						>
							{translate key="publication.editDisabled"}
						</div>
						<tabs class="pkpPublication__tabs" :is-side-tabs="true" :label="publicationTabsLabel">
							<tab id="titleAbstract" label="{translate key="publication.titleAbstract"}">
								<pkp-form v-bind="components.{$smarty.const.FORM_TITLE_ABSTRACT}" @set="set" />
							</tab>
							<tab id="contributors" label="{translate key="publication.contributors"}">
								<div id="contributors-grid" ref="contributors">
									<spinner></spinner>
								</div>
							</tab>
							{if $metadataEnabled}
								<tab id="metadata" label="{translate key="submission.informationCenter.metadata"}">
									<pkp-form v-bind="components.{$smarty.const.FORM_METADATA}" @set="set" />
								</tab>
							{/if}
							<tab v-if="supportsReferences" id="citations" label="{translate key="submission.citations"}">
								<pkp-form v-bind="components.{$smarty.const.FORM_CITATIONS}" @set="set" />
							</tab>
							<tab id="identifiers" label="{translate key="submission.identifiers"}">
								.
							</tab>
							{if $canAccessProduction}
								<tab id="galleys" label="{translate key="submission.layout.galleys"}">
									<div id="representations-grid" ref="representations">
										<spinner></spinner>
									</div>
								</tab>
								<tab id="license" label="{translate key="publication.publicationLicense"}">
									<pkp-form v-bind="components.{$smarty.const.FORM_PUBLICATION_LICENSE}" @set="set" />
								</tab>
								<tab id="issue" label="{translate key="publication.journalEntry"}">
									<pkp-form v-bind="components.{$smarty.const.FORM_JOURNAL_ENTRY}" @set="set" />
								</tab>
							{/if}
							{call_hook name="Template::Workflow::Publication"}
						</tabs>
						<span class="pkpPublication__mask" :class="publicationMaskClasses">
							<spinner></spinner>
						</span>
					</div>
				</tab>
			{/if}
			{call_hook name="Template::Workflow"}
		</tabs>
	</div>
	<script type="text/javascript">
		pkp.registry.init('workflow-{$uuid}', 'WorkflowContainer', {$workflowData|json_encode});
	</script>
</div>

{include file="common/footer.tpl"}
