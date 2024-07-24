<?php
include_once('Services/LDAP/classes/class.ilLDAPQuery.php');


/**
 * Wrapper for ilLDAPQuery implementing tree-merging and
 * value-mapping of HRZ-Marburg account and people LDAP trees.
 */
class ilLDAPQueryUMR extends ilLDAPQuery {
  /** Stores underlying LDAP-Server to fetch query-attributes for */
  protected ilLDAPServer $server;

  /**
   * Creates a new instance of ilLDAPQueryUMR and
   * stores the underlying $a_server into a PROTECTED
   * variable, because ilLDAPQuery is a fat little c*nt!
   *
   * Parameters:
   *  $a_server - LDAP-Server to connect to
   *  $a_url    - (Optional) LDAP-Server url to bind against
   */
  public function __construct(ilLDAPServer $a_server, string $a_url = '')
  {
      parent::__construct($a_server, $a_url);
      $this->server = $a_server;
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
  public function fetchUser(string $a_name): array {
    // Fetch account data
    $users = parent::fetchUser($a_name);

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
  public function fetchUsers(): array {
    // Fetch account data
    $users = parent::fetchUsers();

    // Merge and map account data
    return array_map(function($user_data) {
      return $this->applyUMRLink($user_data);
    }, $users);
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
      $options   = $this->server->toPearAuthArray();
      $result    = $this->query($link, '(objectClass=*)', ilLDAPServer::LDAP_SCOPE_BASE, $options['attributes']);
      $link_data = $result->get();

      // Merge people into account tree (skip dn)
      unset($link_data['dn']);
      $user_data = array_merge($user_data, $link_data);
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
