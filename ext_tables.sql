#
# Table structure extend for table 'tx_sitefactory_domain_model_save'
#
CREATE TABLE tx_sitefactory_domain_model_save (
	root_page_uid int(11) NOT NULL,
	date int(11) DEFAULT 0,
	configuration TEXT DEFAULT ''
);

CREATE TABLE sys_template (
	site_factory_template tinyint(1) unsigned DEFAULT '0' NOT NULL,
);
