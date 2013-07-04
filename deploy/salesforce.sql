CREATE TABLE IF NOT EXISTS `salesforce` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `OrganizationId` varchar(50) NOT NULL,
  `user` varchar(50) NOT NULL,
  `password` varchar(50) NOT NULL,
  `token` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=2 ;

--
-- Volcado de datos para la tabla `salesforce`
--

INSERT INTO `salesforce` (`id`, `OrganizationId`, `user`, `password`, `token`) VALUES
(1, '2FAADB94-5267-11E1-9A0D-0025900034B2', 'steve@globalbrigades.org', 'bbc123', 'VWbL5SqAYaX6T8P9usZt8EkH');
