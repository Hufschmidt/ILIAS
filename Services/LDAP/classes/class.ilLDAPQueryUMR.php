<?php
include_once('Services/LDAP/classes/class.ilLDAPQuery.php');


/**
 * Wrapper for ilLDAPQuery implementing tree-merging and
 * value-mapping of HRZ-Marburg account and people LDAP trees.
 */
class ilLDAPQueryUMR {
  /**
   * References to actual ilLDAPQuery implementation
   * and server options
   */
  protected $query;
  protected $link;
  protected $options;


  /**
   * Funcion: Constructor($a_server, $a_url)
   *  Creates a new LDAPqueryUMR object used to query
   *  HRZ-Marburg like LDAP-Trees.
   *
   * Parameters:
   *  $a_server <ilLDAPServer> ILIAS LDAP Connection implemtation
   *  $a_url <String> [Optional] URL where LDAP server is listerning (Fallback to $a_server config)
   */
  public function __construct(ilLDAPServer $a_server, $a_url = '') {
    $this->query = new ilLDAPQuery($a_server,$a_url = '');

    $this->options = $a_server->toPearAuthArray();
  }


  /**
   * Function: bind($a_binding_type, $a_user_dn, $a_password)
   *  Starts the LDAP bind operation.
   *
   * Parameters:
   *  $a_binding_type <Numeric> - [Optional] Bind with:
   *   Given username/password - IL_LDAP_BIND_AUTH
   *   Attached LDAP-Server admin username/password - IL_LDAP_BIND_ADMIN
   *   Attached LDAP-Server username/password - IL_LDAP_BIND_DEFAULT
   *   (Fallback to attached server config)
   *  $a_user_dn <String> - [Optional] LDAP username to use instead, only with IL_LDAP_BIND_AUTH
   *  $a_password <String> - [Optional] LDAP password to use instead, only with IL_LDAP_BIND_AUTH
   */
  public function bind($a_binding_type = IL_LDAP_BIND_DEFAULT, $a_user_dn = '', $a_password = '') {
    $this->query->bind($a_binding_type, $a_user_dn, $a_password);
  }


  /**
   * Function: fetchUser($a_name)
   *  Fetches all relevant LDAP data for given user.
   *
   * Parameters:
   *  $a_name <String> - External account/ldap-user name
   *
   * Returns:
   *  <Array> - LDAP account data for given user, indexed by ldap-username
   *   $a_name => <Array> LDAP data for a user
   *     sn                => <String> First name
   *     givenname         => <String> Last name
   *     unimranrede       => <String> Gender (f / m)
   *     mail              => <Array> List of email-adresses
   *     dn                => <String> Path in LDAP-Tree
   *     uid               => <String> Unique LDAP username
   *     unimrlinktopeople => <String> Link between account and people tree
   *     ilInternalAccount => null
   */
  public function fetchUser($a_name) {
    // Fetch account data
    $users = $this->query->fetchUser($a_name);

    // Merge and map account data
    return array_map(function($user_data) {
      return $this->applyUMRLink($user_data);
    }, $users);
  }


  /**
   * Function: fetchUsers()
   *  Fetches all relevant LDAP data for all users.
   *
   * Returns:
   *  <Array> - LDAP account data for given user, indexed by ldap-username
   *   [ldap-username] => <Array> LDAP data for user
   *     sn                => <String> First name
   *     givenname         => <String> Last name
   *     unimranrede       => <String> Gender (f / m)
   *     mail              => <Array> List of email-adresses
   *     dn                => <String> Path in LDAP-Tree
   *     uid               => <String> Unique LDAP username
   *     unimrlinktopeople => <String> Link between account and people tree
   *     ilInternalAccount => null
   */
  public function fetchUsers() {
    // Fetch account data
    $users = $this->query->fetchUsers();

    // Merge and map account data
    return array_map(function($user_data) {
      return $this->applyUMRLink($user_data);
    }, $users);
  }


  /**
   * Function: checkGroupMembership($a_ldap_user_name, $ldap_user_data)
   *  Checks if given user member of certain group(s), eg. used to assign roles.
   *
   * Parameters:
   *  $a_ldap_user_name - Username to check
   *  $ldap_user_data - LDAP data for user
   *
   * Returns:
   *  <Boolean> True if group-member, falls otherwise
   */
  public function checkGroupMembership($a_ldap_user_name, $ldap_user_data) {
    // Simply forward to implementation
    return $this->query->checkGroupMembership($a_ldap_user_name, $ldap_user_data);
  }


  /**
   * Function: applyUMRLink($user_data)
   *  Merge data from initial account tree with a second query made
   *  to the people tree.
   *  Also maps data from HRZ values to ILIAS values.
   *
   * Parameters:
   *  $user_data - <Array> LDAP data for a user
   *   sn                => <String> First name
   *   givenname         => <String> Last name
   *   unimranrede       => <String> Gender
   *   mail              => <Array> List of email-adresses
   *   dn                => <String> Path in LDAP-Tree
   *   uid               => <String> Unique LDAP username
   *   unimrlinktopeople => <String> Link between account and people tree
   *   ilInternalAccount => null
   *
   * Returns:
   *  <Array> LDAP data for a user
   *   sn                => <String> First name
   *   givenname         => <String> Last name
   *   unimranrede       => <String> Gender
   *   mail              => <Array> List of email-adresses
   *   dn                => <String> Path in LDAP-Tree
   *   uid               => <String> Unique LDAP username
   *   unimrlinktopeople => <String> Link between account and people tree
   *   ilInternalAccount => null
   */
  protected function applyUMRLink($user_data) {
    // Fetch link to people tree if available
    $link = $user_data['unimrlinktopeople'];

    // Fetch same person from people tree and merge
    if (isset($link) && strlen($link) > 0) {
      $result    = $this->query->query($link, '(objectClass=*)', IL_LDAP_SCOPE_BASE, $this->options['attributes']);
      $link_data = $result->get();

      // Merge people into account tree (skip dn)
      unset($link_data['dn']);
      foreach ($link_data as $attr => $value)
        if (is_int($attr) && array_key_exists($value, $link_data))
          if (array_key_exists($value, $user_data))
            $user_data[$value]              = $link_data[$value];
          else {
            $user_data[$value]              = $link_data[$value];
            $user_data[$user_data['count']] = $value;
            $user_data['count']            += 1;
          }
    }

    // Map all data according to ILIAS values
    foreach ($user_data as $attr => $value) {
      switch ($attr) {
        case 'unimranrede':
          switch (strtolower($value)) {
            case 'herr':
            case 'male':
              $user_data[$attr] = 'm';
            break;
            case 'frau':
            case 'female':
              $user_data[$attr] = 'f';
            break;
            default:
              $user_data[$attr] = '';
          }
        break;
        case 'ou':
          $user_data[$attr] = implode('/', $value);
        break;
      }
    }

    // Return final data
    return $user_data;
  }
}
