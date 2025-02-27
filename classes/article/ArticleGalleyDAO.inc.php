<?php

/**
 * @file classes/article/ArticleGalleyDAO.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ArticleGalleyDAO
 * @ingroup article
 * @see ArticleGalley
 *
 * @brief Operations for retrieving and modifying ArticleGalley objects.
 */

import('classes.article.ArticleGalley');
import('lib.pkp.classes.db.SchemaDAO');
import('lib.pkp.classes.plugins.PKPPubIdPluginDAO');

class ArticleGalleyDAO extends SchemaDAO implements PKPPubIdPluginDAO {
	/** @copydoc SchemaDao::$schemaName */
	public $schemaName = SCHEMA_GALLEY;

	/** @copydoc SchemaDao::$tableName */
	public $tableName = 'publication_galleys';

	/** @copydoc SchemaDao::$settingsTableName */
	public $settingsTableName = 'publication_galley_settings';

	/** @copydoc SchemaDao::$primaryKeyColumn */
	public $primaryKeyColumn = 'galley_id';

	/** @copydoc SchemaDao::$primaryTableColumns */
	public $primaryTableColumns = [
		'fileId' => 'file_id',
		'id' => 'galley_id',
		'isApproved' => 'is_approved',
		'locale' => 'locale',
		'label' => 'label',
		'publicationId' => 'publication_id',
		'seq' => 'seq',
		'urlRemote' => 'remote_url',
		'submissionId' => 'submission_id',
	];

	/**
	 * Return a new data object.
	 * @return ArticleGalley
	 */
	function newDataObject() {
		return new ArticleGalley();
	}

	/**
	 * Retrieve a galley by ID.
	 * @param $pubIdType string One of the NLM pub-id-type values or
	 * 'other::something' if not part of the official NLM list
	 * (see <http://dtd.nlm.nih.gov/publishing/tag-library/n-4zh0.html>).
	 * @param $pubId string
	 * @param $publicationId int
	 * @return ArticleGalley
	 */
	function getGalleyByPubId($pubIdType, $pubId, $publicationId = null) {
		$galleyFactory = $this->getGalleysBySetting('pub-id::'.$pubIdType, $pubId, $publicationId);
		if ($galleyFactory->wasEmpty()) return null;

		assert($galleyFactory->getCount() == 1);
		return $galleyFactory->next();
	}

	/**
	 * Find galleys by querying galley settings.
	 * @param $settingName string
	 * @param $settingValue mixed
	 * @param $publicationId int optional
	 * @param $journalId int optional
	 * @return DAOResultFactory The factory for galleys identified by setting.
	 */
	function getGalleysBySetting($settingName, $settingValue, $publicationId = null, $journalId = null) {
		$params = array($settingName);

		$sql = 'SELECT	g.*
			FROM	publication_galleys g
				INNER JOIN publications p ON p.publication_id = g.publication_id
				INNER JOIN submissions s ON s.current_publication_id = g.publication_id ';
		if (is_null($settingValue)) {
			$sql .= 'LEFT JOIN publication_galley_settings gs ON g.galley_id = gs.galley_id AND gs.setting_name = ?
				WHERE	(gs.setting_value IS NULL OR gs.setting_value = \'\')';
		} else {
			$params[] = (string) $settingValue;
			$sql .= 'INNER JOIN publication_galley_settings gs ON g.galley_id = gs.galley_id
				WHERE	gs.setting_name = ? AND gs.setting_value = ?';
		}
		if ($publicationId) {
			$params[] = (int) $publicationId;
			$sql .= ' AND g.publication_id = ?';
		}
		if ($journalId) {
			$params[] = (int) $journalId;
			$sql .= ' AND s.context_id = ?';
		}
		$sql .= ' ORDER BY s.context_id, g.galley_id';
		$result = $this->retrieve($sql, $params);

		return new DAOResultFactory($result, $this, '_fromRow');
	}

	/**
	 * @copydoc RepresentationDAO::getByPublicationId()
	 */
	function getByPublicationId($publicationId, $contextId = null) {
		$params = array((int) $publicationId);
		if ($contextId) $params[] = (int) $contextId;

		return new DAOResultFactory(
			$this->retrieve(
				'SELECT sf.*, g.*
				FROM publication_galleys g
				INNER JOIN publications p ON (g.publication_id = p.publication_id)
				LEFT JOIN submission_files sf ON (g.file_id = sf.file_id)
				LEFT JOIN submission_files nsf ON (nsf.file_id = g.file_id AND nsf.revision > sf.revision)
				' . ($contextId ? 'LEFT JOIN submissions s ON (s.submission_id = p.submission_id)' : '') .
				'WHERE g.publication_id = ?
					AND nsf.file_id IS NULL ' .
					 ($contextId?' AND s.context_id = ? ':'') .
				'ORDER BY g.seq',
				$params
			),
			$this, '_fromRow'
		);
	}

	/**
	 * Retrieve all galleys of a journal.
	 * @param $journalId int
	 * @return DAOResultFactory
	 */
	function getByContextId($journalId) {
		$result = $this->retrieve(
			'SELECT	sf.*, g.*
			FROM	publication_galleys g
				INNER JOIN publications p ON (p.publication_id = g.publication_id)
				LEFT JOIN submisssions s ON (s.submission_id = p.submission_id)
				LEFT JOIN submission_files sf ON (g.file_id = sf.file_id)
				LEFT JOIN submission_files nsf ON (nsf.file_id = g.file_id AND nsf.revision > sf.revision)
			WHERE	s.context_id = ?
				AND nsf.file_id IS NULL',
			(int) $journalId
		);

		return new DAOResultFactory($result, $this, '_fromRow');
	}

	/**
	 * Retrieve publication galley by public galley id or, failing that,
	 * internal galley ID; public galley ID takes precedence.
	 * @param $galleyId string
	 * @param $publicationId int
	 * @return ArticleGalley object
	 */
	function getByBestGalleyId($galleyId, $publicationId) {
		$galley = null;
		if ($galleyId != '') $galley = $this->getGalleyByPubId('publisher-id', $galleyId, $publicationId);
		if (!isset($galley) && ctype_digit("$galleyId")) $galley = $this->getById((int) $galleyId);
		return $galley;
	}

	/**
	 * @copydoc PKPPubIdPluginDAO::pubIdExists()
	 */
	function pubIdExists($pubIdType, $pubId, $excludePubObjectId, $contextId) {
		$result = $this->retrieve(
			'SELECT COUNT(*)
			FROM publication_galley_settings pgs
				INNER JOIN publication_galleys pg ON pgs.galley_id = pg.galley_id
				INNER JOIN publications p ON pg.publication_id = p.publication_id
				INNER JOIN submissions s ON p.submission_id = s.submission_id
			WHERE pgs.setting_name = ? AND pgs.setting_value = ? AND pgs.galley_id <> ? AND s.context_id = ?',
			array(
				'pub-id::'.$pubIdType,
				$pubId,
				(int) $excludePubObjectId,
				(int) $contextId
			)
		);
		$returner = $result->fields[0] ? true : false;
		$result->Close();
		return $returner;
	}

	/**
	 * @copydoc PKPPubIdPluginDAO::changePubId()
	 */
	function changePubId($pubObjectId, $pubIdType, $pubId) {
		$idFields = array(
			'galley_id', 'locale', 'setting_name'
		);
		$updateArray = array(
			'galley_id' => (int) $pubObjectId,
			'locale' => '',
			'setting_name' => 'pub-id::'.$pubIdType,
			'setting_type' => 'string',
			'setting_value' => (string)$pubId
		);
		$this->replace('publication_galley_settings', $updateArray, $idFields);
	}

	/**
	 * @copydoc PKPPubIdPluginDAO::deletePubId()
	 */
	function deletePubId($pubObjectId, $pubIdType) {
		$settingName = 'pub-id::'.$pubIdType;
		$this->update(
			'DELETE FROM publication_galley_settings WHERE setting_name = ? AND galley_id = ?',
			array(
				$settingName,
				(int)$pubObjectId
			)
		);
		$this->flushCache();
	}

	/**
	 * @copydoc PKPPubIdPluginDAO::deleteAllPubIds()
	 */
	function deleteAllPubIds($contextId, $pubIdType) {
		$settingName = 'pub-id::'.$pubIdType;

		$galleys = $this->getByContextId($contextId);
		while ($galley = $galleys->next()) {
			$this->update(
				'DELETE FROM publication_galley_settings WHERE setting_name = ? AND galley_id = ?',
				array(
					$settingName,
					(int)$galley->getId()
				)
			);
		}
		$this->flushCache();
	}

	/**
	 * Get all published submission galleys (eventually with a pubId assigned and) matching the specified settings.
	 * @param $contextId integer optional
	 * @param $pubIdType string
	 * @param $title string optional
	 * @param $author string optional
	 * @param $issueId integer optional
	 * @param $pubIdSettingName string optional
	 * (e.g. medra::status or medra::registeredDoi)
	 * @param $pubIdSettingValue string optional
	 * @param $rangeInfo DBResultRange optional
	 * @return DAOResultFactory
	 */
	function getExportable($contextId, $pubIdType = null, $title = null, $author = null, $issueId = null, $pubIdSettingName = null, $pubIdSettingValue = null, $rangeInfo = null) {
		$params = array();
		if ($pubIdSettingName) {
			$params[] = $pubIdSettingName;
		}
		$params[] = (int) $contextId;
		if ($pubIdType) {
			$params[] = 'pub-id::'.$pubIdType;
		}
		if ($title) {
			$params[] = 'title';
			$params[] = '%' . $title . '%';
		}
		if ($author) array_push($params, $authorQuery = '%' . $author . '%', $authorQuery);
		if ($issueId) {
			$params[] = (int) $issueId;
		}
		import('classes.plugins.PubObjectsExportPlugin');
		if ($pubIdSettingName && $pubIdSettingValue && $pubIdSettingValue != EXPORT_STATUS_NOT_DEPOSITED) {
			$params[] = $pubIdSettingValue;
		}

		import('classes.submission.Submission'); // STATUS_DECLINED constant
		$result = $this->retrieveRange(
				'SELECT	sf.*, g.*
			FROM	submission_galleys g
				JOIN submissions s ON (s.submission_id = g.submission_id AND s.status <> ' . STATUS_DECLINED .')
				LEFT JOIN publications p ON (p.submission_id = g.submission_id)
				LEFT JOIN publication_settings ps ON (ps.submission_id = g.submission_id)
				JOIN issues i ON (ps.issue_id = i.issue_id)
				LEFT JOIN submission_files sf ON (g.file_id = sf.file_id)
				LEFT JOIN submission_files nsf ON (nsf.file_id = g.file_id AND nsf.revision > sf.revision AND nsf.file_id IS NULL )
				' . ($pubIdType != null?' LEFT JOIN submission_galley_settings gs ON (g.galley_id = gs.galley_id)':'')
				. ($title != null?' LEFT JOIN submission_settings sst ON (s.submission_id = sst.submission_id)':'')
				. ($author != null?' LEFT JOIN authors au ON (s.submission_id = au.submission_id)
						LEFT JOIN author_settings asgs ON (asgs.author_id = au.author_id AND asgs.setting_name = \''.IDENTITY_SETTING_GIVENNAME.'\')
						LEFT JOIN author_settings asfs ON (asfs.author_id = au.author_id AND asfs.setting_name = \''.IDENTITY_SETTING_FAMILYNAME.'\')
					':'')
				. ($pubIdSettingName != null?' LEFT JOIN submission_galley_settings gss ON (g.galley_id = gss.galley_id AND gss.setting_name = ?)':'') .'
			WHERE
				i.published = 1 AND s.context_id = ?
				' . ($pubIdType != null?' AND gs.setting_name = ? AND gs.setting_value IS NOT NULL':'')
				. ($title != null?' AND (sst.setting_name = ? AND sst.setting_value LIKE ?)':'')
				. ($author != null?' AND (asgs.setting_value LIKE ? OR asfs.setting_value LIKE ?)':'')
				. ($issueId != null?' AND (ps.setting_name = "issueId" AND ps.setting_value = ?':'')
				. (($pubIdSettingName != null && $pubIdSettingValue != null && $pubIdSettingValue == EXPORT_STATUS_NOT_DEPOSITED)?' AND gss.setting_value IS NULL':'')
				. (($pubIdSettingName != null && $pubIdSettingValue != null && $pubIdSettingValue != EXPORT_STATUS_NOT_DEPOSITED)?' AND gss.setting_value = ?':'')
				. (($pubIdSettingName != null && is_null($pubIdSettingValue))?' AND (gss.setting_value IS NULL OR gss.setting_value = \'\')':'') .'
				ORDER BY p.date_published DESC, s.submission_id DESC, g.galley_id DESC',
			$params,
			$rangeInfo
		);

		return new DAOResultFactory($result, $this, '_fromRow');
	}
}


