<?hh

SessionUtils::sessionStart();
SessionUtils::enforceLogin();

class GameAjaxController extends AjaxController {
  <<__Override>>
  protected function getFilters(): array<string, mixed> {
    return array(
      'POST' => array(
        'level_id'    => FILTER_VALIDATE_INT,
        'answer'      => FILTER_UNSAFE_RAW,
        'csrf_token'  => FILTER_UNSAFE_RAW,
        'action'      => array(
          'filter'      => FILTER_VALIDATE_REGEXP,
          'options'     => array(
            'regexp'      => '/^[\w-]+$/'
          ),
        ),
        'page'      => array(
          'filter'      => FILTER_VALIDATE_REGEXP,
          'options'     => array(
            'regexp'      => '/^[\w-]+$/'
          ),
        )
      )
    );
  }

  <<__Override>>
  protected function getActions(): array<string> {
    return array(
      'answer_level',
      'get_hint',
      'open_level',
    );
  }

  <<__Override>>
  protected function handleAction(string $action, array<string, mixed> $params): string {
    if ($action !== 'none') {
      // CSRF check
      if (idx($params, 'csrf_token') !== SessionUtils::CSRFToken()) {
        return Utils::error_response('CSRF token is invalid', 'game');
      }
    }

    switch ($action) {
    case 'none':
      return Utils::error_response('Invalid action', 'game');
    case 'answer_level':
      if (Configuration::get('scoring')->getValue() === '1') {
        // Check if level is not a base
        if (Level::checkBase(
          must_have_int($params, 'level_id')
        )) {
          return Utils::error_response('Failed', 'game');
        // Check if answer is valid
        } else if (Level::checkAnswer(
          must_have_int($params, 'level_id'),
          must_have_string($params, 'answer')
        )) {
          // Give points!
          Level::scoreLevel(
            must_have_int($params, 'level_id'),
            SessionUtils::sessionTeam()
          );
          // Update teams last score
          Team::lastScore(SessionUtils::sessionTeam());
          return Utils::ok_response('Success', 'game');
        } else {
          FailureLog::logFailedScore(
            must_have_int($params, 'level_id'),
            SessionUtils::sessionTeam(),
            must_have_string($params, 'answer')
          );
          return Utils::error_response('Failed', 'game');
        }
      } else {
        return Utils::error_response('Failed', 'game');
      }
    case 'get_hint':
      $requested_hint = Level::getLevelHint(
        must_have_int($params, 'level_id'),
        SessionUtils::sessionTeam()
      );
      if ($requested_hint) {
        return Utils::hint_response($requested_hint, 'OK');
      } else {
        return Utils::hint_response('', 'ERROR');
      }
    case 'open_level':
      return Utils::ok_response('Success', 'admin');
    default:
      return Utils::error_response('Invalid action', 'game');
    }
  }
}
