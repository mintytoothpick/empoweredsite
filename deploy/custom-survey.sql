
CREATE TABLE IF NOT EXISTS `survey_global_student_embassy` (
  `Id` int(11) NOT NULL AUTO_INCREMENT,
  `FirstName` varchar(50) NOT NULL,
  `MiddleName` varchar(50) NOT NULL,
  `LastName` varchar(50) NOT NULL,
  `PreferredName` varchar(50) NOT NULL,
  `DateBirth` date NOT NULL,
  `Address` varchar(150) NOT NULL,
  `ParticipantCellNum` varchar(30) NOT NULL,
  `ParticipantEmail` varchar(30) NOT NULL,
  `GradeYearSchool` varchar(30) NOT NULL,
  `Gender` tinyint(4) NOT NULL,
  `Parent1` varchar(50) NOT NULL,
  `Parent2` varchar(50) NOT NULL,
  `ParentAddress` varchar(150) NOT NULL,
  `EmergencyName1` varchar(50) NOT NULL,
  `EmergencyRelation1` varchar(30) NOT NULL,
  `EmergencyEmail1` varchar(50) NOT NULL,
  `EmergencyDayPhone1` varchar(30) NOT NULL,
  `EmergencyEveningPhone1` varchar(30) NOT NULL,
  `EmergencyCellPhone1` varchar(30) NOT NULL,
  `EmergencyName2` varchar(50) NOT NULL,
  `EmergencyRelation2` varchar(30) DEFAULT NULL,
  `EmergencyEmail2` varchar(50) DEFAULT NULL,
  `EmergencyDayPhone2` varchar(30) DEFAULT NULL,
  `EmergencyEveningPhone2` varchar(30) DEFAULT NULL,
  `EmergencyCellPhone2` varchar(30) DEFAULT NULL,
  `BleedingClottingDisorders` tinyint(4) NOT NULL DEFAULT '0',
  `Asthma` tinyint(4) NOT NULL DEFAULT '0',
  `Diabetes` tinyint(4) NOT NULL DEFAULT '0',
  `EarInfections` tinyint(4) NOT NULL DEFAULT '0',
  `HeartDefectsHypertension` tinyint(4) NOT NULL DEFAULT '0',
  `PsychiatricTreatment` tinyint(4) NOT NULL DEFAULT '0',
  `SeizureDisorder` tinyint(4) DEFAULT NULL,
  `ImmunoCompromised` tinyint(4) DEFAULT NULL,
  `SleepWalking` tinyint(4) DEFAULT NULL,
  `BedWetting` tinyint(4) DEFAULT NULL,
  `HospitalizedLast5Years` tinyint(4) DEFAULT NULL,
  `ChickenPox` tinyint(4) DEFAULT NULL,
  `Measles` tinyint(4) DEFAULT NULL,
  `Mumps` tinyint(4) DEFAULT NULL,
  `OtherDiseases` tinyint(4) DEFAULT NULL,
  `DateLastTetanusShot` varchar(30) DEFAULT NULL,
  `HayFever` tinyint(4) DEFAULT NULL,
  `Iodine` tinyint(4) DEFAULT NULL,
  `Mangos` tinyint(4) DEFAULT NULL,
  `PoisonOak` tinyint(4) DEFAULT NULL,
  `Penicillin` tinyint(4) DEFAULT NULL,
  `BeesWaspsInsects` tinyint(4) DEFAULT NULL,
  `Food` tinyint(4) DEFAULT NULL,
  `OtherAllergies` tinyint(4) DEFAULT NULL,
  `EpinephrinePen` tinyint(4) DEFAULT NULL,
  `Inhaler` tinyint(4) DEFAULT NULL,
  `Explanation` text,
  `Passport` tinyint(4) DEFAULT NULL,
  `PassportCountry` varchar(50) DEFAULT NULL,
  `PassportName` varchar(50) DEFAULT NULL,
  `PassportExpirationDate` date DEFAULT NULL,
  `CountryBirth` varchar(60) DEFAULT NULL,
  `Citizenship` varchar(60) DEFAULT NULL,
  `Grade` varchar(30) DEFAULT NULL,
  `GPA` varchar(30) DEFAULT NULL,
  `SpanishListening` varchar(12) DEFAULT NULL,
  `SpanishReadingWriting` varchar(12) DEFAULT NULL,
  `SpanishSpeaking` varchar(12) DEFAULT NULL,
  `TraveledOutsideUS` tinyint(4) DEFAULT NULL,
  `TraveledDevelopingWorld` tinyint(4) DEFAULT NULL,
  `Experiences` text,
  `SignatureName` varchar(120) DEFAULT NULL,
  `SignatureParentName` varchar(120) DEFAULT NULL,
  `FundraisingSupportMaterials` tinyint(4) DEFAULT NULL,
  `UserId` varchar(50) NOT NULL,
  `ProjectId` varchar(50) NOT NULL,
  `GroupId` varchar(50) NOT NULL,
  `date` date NOT NULL,
  PRIMARY KEY (`Id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;
