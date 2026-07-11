import React, { memo, useState } from 'react';
import { ServerEggVariable } from '@/api/server/types';
import { usePermissions } from '@/plugins/usePermissions';
import InputSpinner from '@/components/elements/InputSpinner';
import Input from '@/components/elements/Input';
import Switch from '@/components/elements/Switch';
import { debounce } from 'debounce';
import updateStartupVariable from '@/api/server/updateStartupVariable';
import useFlash from '@/plugins/useFlash';
import FlashMessageRender from '@/components/FlashMessageRender';
import getServerStartup from '@/api/swr/getServerStartup';
import Select from '@/components/elements/Select';
import isEqual from 'react-fast-compare';
import { ServerContext } from '@/state/server';
import StartupFormRow from '@/components/server/startup/StartupFormRow';
import tw from 'twin.macro';

interface Props {
    variable: ServerEggVariable;
}

const VariableBox = ({ variable }: Props) => {
    const FLASH_KEY = `server:startup:${variable.envVariable}`;

    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const [loading, setLoading] = useState(false);
    const [canEdit] = usePermissions(['startup.update']);
    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const { mutate } = getServerStartup(uuid);

    const setVariableValue = debounce((value: string) => {
        setLoading(true);
        clearFlashes(FLASH_KEY);

        updateStartupVariable(uuid, variable.envVariable, value)
            .then(([response, invocation]) =>
                mutate(
                    (data) => ({
                        ...data,
                        invocation,
                        variables: (data.variables || []).map((v) =>
                            v.envVariable === response.envVariable ? response : v
                        ),
                    }),
                    false
                )
            )
            .catch((error) => {
                console.error(error);
                clearAndAddHttpError({ error, key: FLASH_KEY });
            })
            .then(() => setLoading(false));
    }, 500);

    const useSwitch = variable.rules.some(
        (v) => v === 'boolean' || v === 'in:0,1' || v === 'in:1,0' || v === 'in:true,false' || v === 'in:false,true'
    );
    const isStringSwitch = variable.rules.some((v) => v === 'string');
    const selectRule = variable.rules.find((v) => v.startsWith('in:'));
    const selectValues = selectRule
        ? selectRule
              .replace('in:', '')
              .split(',')
              .map((value) => value.trim())
              .filter(Boolean)
        : [];

    return (
        <StartupFormRow
            htmlFor={variable.envVariable}
            label={
                <>
                    {!variable.isEditable && (
                        <span css={tw`bg-neutral-700 text-2xs py-0.5 px-2 rounded-full mr-2 inline-block mb-1`}>
                            Read Only
                        </span>
                    )}
                    {variable.name}
                </>
            }
            description={variable.description || undefined}
        >
            <FlashMessageRender byKey={FLASH_KEY} className='mb-2' />
            <InputSpinner visible={loading}>
                {useSwitch ? (
                    <Switch
                        readOnly={!canEdit || !variable.isEditable}
                        name={variable.envVariable}
                        id={variable.envVariable}
                        defaultChecked={
                            isStringSwitch ? variable.serverValue === 'true' : variable.serverValue === '1'
                        }
                        onChange={() => {
                            if (canEdit && variable.isEditable) {
                                if (isStringSwitch) {
                                    setVariableValue(variable.serverValue === 'true' ? 'false' : 'true');
                                } else {
                                    setVariableValue(variable.serverValue === '1' ? '0' : '1');
                                }
                            }
                        }}
                    />
                ) : selectValues.length > 0 ? (
                    <Select
                        id={variable.envVariable}
                        onChange={(e) => setVariableValue(e.target.value)}
                        name={variable.envVariable}
                        defaultValue={variable.serverValue ?? variable.defaultValue}
                        disabled={!canEdit || !variable.isEditable}
                    >
                        {selectValues.map((selectValue) => (
                            <option key={selectValue} value={selectValue}>
                                {selectValue}
                            </option>
                        ))}
                    </Select>
                ) : (
                    <Input
                        id={variable.envVariable}
                        onKeyUp={(e) => {
                            if (canEdit && variable.isEditable) {
                                setVariableValue(e.currentTarget.value);
                            }
                        }}
                        readOnly={!canEdit || !variable.isEditable}
                        name={variable.envVariable}
                        defaultValue={variable.serverValue ?? ''}
                        placeholder={variable.defaultValue}
                    />
                )}
            </InputSpinner>
        </StartupFormRow>
    );
};

export default memo(VariableBox, isEqual);
