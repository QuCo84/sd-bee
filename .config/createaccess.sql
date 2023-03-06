/**
  *  SD bee Access database initialisation
  */

/**
* Users
*/
DROP TABLE IF EXISTS `Users`;
CREATE TABLE `Users` (
  'name' text NOT NULL,               /* User's login */
  'password' text DEFAULT NULL,       /* User's password */
  'language' text DEFAULT NULL,       /* User's language */
  'doc-storage' text NOT NULL,        /* Name of storage to use for documents */
  'resource-storage' text,            /* Name of storage to use for private (not built-in) resources */
  'service-gateway' text,             /* Name of service gateway */
  'service-username' text,            /* Username of service account */
  'service-password' text,            /* Password of account on service gateway */
  'top-doc-dir' text,                 /* Top directory in doc storage */
  'home' text,                        /* Collection or task to display for home */
  'prefix' text,                      /* Default prefix to add to filenames in doc storage */
  'key' text                          /* Default key to use for crypting files in doc storage */  
);
INSERT INTO `Users` VALUES
    ('{admin-user}', 'CRYPT({admin-pass})', 'FR', 'private-storage', '', 'https://www.sd-bee.com/webdesk/', '{admin-user}', '{admin-pass}', '', '{docName}_Tasks', '{token}', ''),
    ('demo', 'CRYPT(demo)', 'FR', 'private-storage', '', 'https://www.sd-bee.com/webdesk/', 'demo', 'demo', '', 'A0012345678920001_trialhome', 'ymbNpnZm', '');

/**
* Members = remember me cookies
*/
DROP TABLE IF EXISTS `Members`;
CREATE TABLE `Members` (
  token text NOT NULL,              /* Value of cookie */
  ip text DEFAULT NULL,             /* IP address associated with token */
  userId int(11) NOT NULL,          /* Id of associated user */
  validDate int(11) DEFAULT NULL    /* Validity date */
);


DROP TABLE IF EXISTS `Docs`;
CREATE TABLE `Docs` (
  name text NOT NULL,
  label text NOT NULL,
  type int(5),
  model text DEFAULT NULL,
  description text DEFAULT NULL,
  params text DEFAULT NULL,
  prefix text DEFAULT NULL,
  created int(11) DEFAULT NULL,
  updated int(11) DEFAULT NULL,
  state text DEFAULT NULL,
  progress int(5),
  deadline int(11)
);
INSERT INTO `Docs` VALUES
    ('A00123456789200001_trialhome', 'trialhome', 1, 'none', 'trial dir', '', '', 0, 0, '', 0, 0),
    ('A0000002NHSEB0000M_Repageaf', 'trial doc', 2, 'ASS000000000301_System', 'test doc derived from repage test', '', '', 0, 0, '', 0, 0),
    ('A0000000020000002_Share', '{!Share!}', 1, 'none', '{!Shared documents!}', '', '', 0, 0, '', 0, 0),
    ('{docName}_Tasks', '{!Tasks!}', 2, 'none', '{!My tasks!}', '{"state":"new"}', '', 0, 0, '', 0, 0),
    ('{docName}_GettingStarted', '{!Guide de démarrage!}', 1, 'A00000001LQ09000M_Help train', '{!Tutoriaux de 10 minutes pour découvrir SD bee!}', '{"state":"new"}', '', 0, 0, '', 0, 0),
    ('Z00000000100000001_wastebin', '{!Wastebin!}', 1, 'none', '{!Recycled tasks!}', '', '', 0, 0, '', 0, 0),
    ('Z00000010VKK800001_UserConfig', '{!UserConfig!}', 2, 'A0000000V3IL70000M_User2', '{!My preferences and parameters!}', '{"state":"new"}', '', 0, 0, '', 0, 0);

/* UserLinks : userId, isUser, targetId, access, */
DROP TABLE IF EXISTS `UserLinks`;
CREATE TABLE `UserLinks` (
  userId int(11) NOT NULL,
  isUser tinyint(1) DEFAULT NULL,
  targetId int(11) NOT NULL,
  access int(11) DEFAULT NULL,
  PRIMARY KEY( userId, isUser, targetId)
) WITHOUT ROWID;
INSERT INTO `UserLinks` VALUES ( 1, 0, 3, 7), ( 1, 0, 4, 7), ( 1, 0, 6, 7), ( 1, 0, 7, 7), ( 2, 0, 1, 7);

/*  CollectionLinks : collectionId, isDoc, targetId, access*/
DROP TABLE IF EXISTS `CollectionLinks`;
CREATE TABLE `CollectionLinks` (
  collectionId int(11) NOT NULL,
  isDoc tinyint(1) DEFAULT NULL,
  targetId int(11) NOT NULL,
  access int(11) DEFAULT NULL,
  PRIMARY KEY( collectionId, isDoc, targetId)
) WITHOUT ROWID;
INSERT INTO CollectionLinks VALUES ( 1, 1, 2, 7),( 4, 1, 5, 7);

/* Clips */
DROP TABLE IF EXISTS 'Clips';
CREATE TABLE 'Clips' (
  userId int(11) NOT NULL,
  type text NOT NULL,
  content text DEFAULT NULL
);

/* Service log */
DROP TABLE IF EXISTS 'ServiceLog';
CREATE TABLE 'ServiceLog' (
  name text NOT NULL,
  userId int(11) NOT NULL,
  nevent text NOT NULL,
  iresult int( 11) DEFAULT NULL,
  tdetails text DEFAULT NULL
);

/* LoadedFiles : date, filename, report*/
DROP TABLE IF EXISTS 'LoadedFiles';
CREATE TABLE 'LoadedFiles' (
  date int(11) NOT NULL,
  file text NOT NULL,
  report text 
);
